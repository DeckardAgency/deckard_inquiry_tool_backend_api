<?php

namespace App\Controller;

use App\Entity\MediaItem;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\Serializer\SerializerInterface;

#[AsController]
final class CreateMediaItemAction extends AbstractController implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private SerializerInterface $serializer,
        private string $uploadDirectory
    ) {
        $this->uploadDirectory = $uploadDirectory;
    }

    public function __invoke(Request $request): Response
    {
        $mediaItem = $this->handleUpload($request);

        $jsonContent = $this->serializer->serialize($mediaItem, 'json', ['groups' => ['media_item:read']]);

        return new JsonResponse(
            $jsonContent,
            Response::HTTP_CREATED,
            [],
            true
        );
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MediaItem
    {
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            throw new BadRequestHttpException('Request is required');
        }

        return $this->handleUpload($request);
    }

    private function handleUpload(Request $request): MediaItem
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            throw new BadRequestHttpException('"file" is required');
        }

        // Validate file size (5MB max)
        $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($uploadedFile->getSize() > $maxFileSize) {
            throw new BadRequestHttpException(sprintf(
                'File size exceeds maximum allowed size of %d MB',
                $maxFileSize / 1024 / 1024
            ));
        }

        // Validate MIME type using finfo for content-based detection
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMimeType = $finfo->file($uploadedFile->getPathname());

        // Whitelist of allowed MIME types
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];

        if (!in_array($detectedMimeType, $allowedMimeTypes, true)) {
            throw new BadRequestHttpException(sprintf(
                'File type "%s" is not allowed. Allowed types: %s',
                $detectedMimeType,
                implode(', ', $allowedMimeTypes)
            ));
        }

        // Verify file is not empty
        if ($uploadedFile->getSize() === 0) {
            throw new BadRequestHttpException('File is empty');
        }

        $filesystem = new Filesystem();

        // Ensure upload directory exists
        if (!$filesystem->exists($this->uploadDirectory)) {
            $filesystem->mkdir($this->uploadDirectory);
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
            $uploadedFile->move(
                $this->uploadDirectory,
                $newFilename
            );
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Failed to upload file: ' . $e->getMessage());
        }

        $mediaItem->setFilename($newFilename);
        $mediaItem->setMimeType($mimeType);
        $mediaItem->setFilePath('/uploads/'.$newFilename);

        $this->entityManager->persist($mediaItem);
        $this->entityManager->flush();

        return $mediaItem;
    }
}
