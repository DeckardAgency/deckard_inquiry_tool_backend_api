<?php

namespace App\Controller;

use App\Entity\Documentation;
use App\Entity\MediaItem;
use App\Repository\DocumentationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;

#[AsController]
#[Route('/api/v1/documentations')]
class DocumentationMediaController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentationRepository $documentationRepository,
        private SluggerInterface $slugger,
        private SerializerInterface $serializer,
        private string $uploadDirectory
    ) {}

    /**
     * Upload an image for a specific documentation
     */
    #[Route('/{id}/media', name: 'documentation_upload_media', methods: ['POST'])]
    public function uploadMedia(string $id, Request $request): Response
    {
        $documentation = $this->documentationRepository->find($id);

        if (!$documentation) {
            throw new NotFoundHttpException('Documentation not found');
        }

        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            throw new BadRequestHttpException('"file" is required');
        }

        // Validate file size (10MB max for documentation images)
        $maxFileSize = 10 * 1024 * 1024;
        if ($uploadedFile->getSize() > $maxFileSize) {
            throw new BadRequestHttpException(sprintf(
                'File size exceeds maximum allowed size of %d MB',
                $maxFileSize / 1024 / 1024
            ));
        }

        // Validate MIME type - only images allowed for documentation
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMimeType = $finfo->file($uploadedFile->getPathname());

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ];

        if (!in_array($detectedMimeType, $allowedMimeTypes, true)) {
            throw new BadRequestHttpException(sprintf(
                'File type "%s" is not allowed. Only images are allowed: %s',
                $detectedMimeType,
                implode(', ', $allowedMimeTypes)
            ));
        }

        // Verify file is not empty
        if ($uploadedFile->getSize() === 0) {
            throw new BadRequestHttpException('File is empty');
        }

        $filesystem = new Filesystem();

        // Create documentation-specific upload directory
        $docUploadDir = $this->uploadDirectory . '/documentation';
        if (!$filesystem->exists($docUploadDir)) {
            $filesystem->mkdir($docUploadDir);
        }

        $mediaItem = new MediaItem();

        // Get mime type before moving the file
        $mimeTypes = new MimeTypes();
        $mimeType = $mimeTypes->guessMimeType($uploadedFile->getPathname());

        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $uploadedFile->guessExtension() ?? 'bin';
        $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid(), $extension);

        // Move file to permanent location
        try {
            $uploadedFile->move($docUploadDir, $newFilename);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Failed to upload file: ' . $e->getMessage());
        }

        $mediaItem->setFilename($newFilename);
        $mediaItem->setMimeType($mimeType);
        $mediaItem->setFilePath('/uploads/documentation/' . $newFilename);
        $mediaItem->setDocumentation($documentation);

        $this->entityManager->persist($mediaItem);
        $this->entityManager->flush();

        $jsonContent = $this->serializer->serialize($mediaItem, 'json', ['groups' => ['media_item:read']]);

        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    /**
     * Get all media for a specific documentation
     */
    #[Route('/{id}/media', name: 'documentation_get_media', methods: ['GET'])]
    public function getMedia(string $id): Response
    {
        $documentation = $this->documentationRepository->find($id);

        if (!$documentation) {
            throw new NotFoundHttpException('Documentation not found');
        }

        $media = $documentation->getMedia()->toArray();

        $jsonContent = $this->serializer->serialize($media, 'json', ['groups' => ['media_item:read']]);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    /**
     * Delete a media item from documentation
     */
    #[Route('/{docId}/media/{mediaId}', name: 'documentation_delete_media', methods: ['DELETE'])]
    public function deleteMedia(string $docId, string $mediaId): Response
    {
        $documentation = $this->documentationRepository->find($docId);

        if (!$documentation) {
            throw new NotFoundHttpException('Documentation not found');
        }

        $mediaItem = null;
        foreach ($documentation->getMedia() as $media) {
            if ($media->getId()->toRfc4122() === $mediaId) {
                $mediaItem = $media;
                break;
            }
        }

        if (!$mediaItem) {
            throw new NotFoundHttpException('Media item not found in this documentation');
        }

        // Delete the file from filesystem
        $filesystem = new Filesystem();
        $filePath = $this->uploadDirectory . str_replace('/uploads', '', $mediaItem->getFilePath());
        if ($filesystem->exists($filePath)) {
            $filesystem->remove($filePath);
        }

        $this->entityManager->remove($mediaItem);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
