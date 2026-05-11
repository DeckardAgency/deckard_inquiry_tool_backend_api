<?php

namespace App\Controller;

use App\Repository\DispatchDocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class DispatchDocumentDownloadController extends AbstractController
{
    public function __construct(
        private readonly DispatchDocumentRepository $documentRepository,
        private readonly string $uploadsDir
    ) {
    }

    #[Route('/api/v1/dispatch-documents/{id}/download', name: 'api_dispatch_document_download', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        // Validate UUID
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException('Invalid document ID format');
        }

        // Find the document
        $document = $this->documentRepository->find($uuid);

        if (!$document) {
            throw new NotFoundHttpException('Document not found');
        }

        // Check if user has access to the order
        $order = $document->getOrder();
        if ($order) {
            $this->denyAccessUnlessGranted('VIEW', $order);
        }

        // Get file path
        $filePath = $this->uploadsDir . '/' . $document->getFilePath();

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Document file not found');
        }

        // Return binary file response
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getOriginalFilename()
        );

        return $response;
    }
}
