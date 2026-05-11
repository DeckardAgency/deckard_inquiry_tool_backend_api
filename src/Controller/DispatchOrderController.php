<?php

namespace App\Controller;

use App\Entity\DispatchDocument;
use App\Entity\Order;
use App\Entity\User;
use App\Message\OrderDispatchedMessage;
use App\Repository\OrderRepository;
use App\Service\OrderLogService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class DispatchOrderController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    private const MAX_FILE_SIZE = 10485760; // 10MB

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly OrderLogService $orderLogService,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
        private readonly string $uploadsDir
    ) {
    }

    #[Route('/api/v1/orders/shipping-providers', name: 'api_order_shipping_providers', methods: ['GET'])]
    public function getShippingProviders(): JsonResponse
    {
        return new JsonResponse(array_map('strtoupper', Order::ALLOWED_CARRIERS));
    }

    #[Route('/api/v1/orders/{id}/dispatch', name: 'api_order_dispatch', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        // Validate UUID
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException('Invalid order ID format');
        }

        // Find the order
        $order = $this->orderRepository->find($uuid);

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        // Check if user has access to modify this order
        $this->denyAccessUnlessGranted('EDIT', $order);

        // Validate order status - must be 'confirmed' to dispatch
        if ($order->getStatus() !== Order::STATUS_CONFIRMED) {
            throw new BadRequestHttpException('Order must be in "confirmed" status to dispatch. Current status: ' . $order->getStatus());
        }

        // Get uploaded files
        $files = $request->files->all();

        // Check for files in nested structure (common with FormData)
        if (isset($files['files'])) {
            $files = $files['files'];
        }

        // Flatten files if they're in an indexed array
        $uploadedFiles = [];
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                foreach ($file as $f) {
                    $uploadedFiles[] = $f;
                }
            } else {
                $uploadedFiles[] = $file;
            }
        }

        // Validate at least one file is uploaded
        if (empty($uploadedFiles)) {
            throw new BadRequestHttpException('At least one document must be uploaded when dispatching an order');
        }

        // Get tracking info from request
        $trackingNumber = $request->request->get('trackingNumber');
        $trackingCarrier = $request->request->get('trackingCarrier');
        $trackingUrl = $request->request->get('trackingUrl');
        $dispatchNotes = $request->request->get('dispatchNotes');

        // Validate required fields
        if (empty($trackingNumber)) {
            throw new BadRequestHttpException('Tracking number is required');
        }

        if (empty($trackingCarrier)) {
            throw new BadRequestHttpException('Tracking carrier is required');
        }

        $trackingCarrier = strtolower($trackingCarrier);
        if (!in_array($trackingCarrier, Order::ALLOWED_CARRIERS, true)) {
            throw new BadRequestHttpException(
                'Invalid shipping provider. Allowed options: ' . implode(', ', array_map('strtoupper', Order::ALLOWED_CARRIERS))
            );
        }

        // Get current user
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        // Process uploaded files
        $savedDocuments = [];
        $uploadPath = $this->getUploadPath($order);

        // Ensure upload directory exists
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        foreach ($uploadedFiles as $index => $uploadedFile) {
            if (!$uploadedFile || !$uploadedFile->isValid()) {
                $this->logger->warning('Invalid file upload', [
                    'order_id' => $id,
                    'file_index' => $index,
                    'error' => $uploadedFile?->getErrorMessage() ?? 'File is null'
                ]);
                continue;
            }

            // Validate file size
            if ($uploadedFile->getSize() > self::MAX_FILE_SIZE) {
                throw new BadRequestHttpException(
                    sprintf('File "%s" exceeds maximum size of 10MB', $uploadedFile->getClientOriginalName())
                );
            }

            // Validate mime type
            $mimeType = $uploadedFile->getMimeType();
            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                throw new BadRequestHttpException(
                    sprintf('File type "%s" is not allowed. Allowed types: PDF, images, Excel, Word documents', $mimeType)
                );
            }

            // Generate unique filename
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $uploadedFile->guessExtension() ?: 'bin';
            $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFilename);
            $newFilename = $safeFilename . '_' . uniqid() . '.' . $extension;

            // Capture file info BEFORE moving (temp file is deleted after move)
            $fileSize = $uploadedFile->getSize();
            $clientOriginalName = $uploadedFile->getClientOriginalName();

            // Determine document type from file extension/name
            $documentType = $this->determineDocumentType($clientOriginalName, $mimeType);

            // Move the file
            try {
                $uploadedFile->move($uploadPath, $newFilename);
            } catch (\Exception $e) {
                $this->logger->error('Failed to move uploaded file', [
                    'order_id' => $id,
                    'filename' => $clientOriginalName,
                    'error' => $e->getMessage()
                ]);
                throw new BadRequestHttpException('Failed to upload file: ' . $clientOriginalName);
            }

            // Create DispatchDocument entity
            $document = new DispatchDocument();
            $document->setOrder($order);
            $document->setFilename($newFilename);
            $document->setOriginalFilename($clientOriginalName);
            $document->setMimeType($mimeType);
            $document->setFilePath('dispatch/' . $order->getId()->toRfc4122() . '/' . $newFilename);
            $document->setDocumentType($documentType);
            $document->setFileSize($fileSize);

            $this->entityManager->persist($document);
            $order->addDispatchDocument($document);
            $savedDocuments[] = $document;
        }

        if (empty($savedDocuments)) {
            throw new BadRequestHttpException('No valid documents were uploaded');
        }

        // Update order status and tracking info
        $previousStatus = $order->getStatus();
        $order->setStatus(Order::STATUS_DISPATCHED);
        $order->setTrackingNumber($trackingNumber);
        $order->setTrackingCarrier($trackingCarrier);
        $order->setTrackingUrl($trackingUrl ?: $order->generateTrackingUrl());
        $order->setDispatchedAt(new \DateTime());
        $order->setDispatchedBy($currentUser);

        // Log the status change
        $this->orderLogService->logStatusChange(
            $order,
            $previousStatus,
            Order::STATUS_DISPATCHED,
            'Order dispatched with ' . count($savedDocuments) . ' document(s)',
            [
                'tracking_number' => $trackingNumber,
                'tracking_carrier' => $trackingCarrier,
                'documents_count' => count($savedDocuments),
                'dispatch_notes' => $dispatchNotes
            ]
        );

        // Persist changes
        $this->entityManager->flush();

        // Dispatch message for email notifications
        $this->messageBus->dispatch(new OrderDispatchedMessage(
            $order->getId(),
            $currentUser?->getId(),
            array_map(fn($doc) => $doc->getId()->toRfc4122(), $savedDocuments)
        ));

        $this->logger->info('Order dispatched successfully', [
            'order_id' => $id,
            'order_number' => $order->getOrderNumber(),
            'tracking_number' => $trackingNumber,
            'documents_count' => count($savedDocuments)
        ]);

        // Return updated order data
        return new JsonResponse([
            'id' => $order->getId()->toRfc4122(),
            'orderNumber' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'trackingNumber' => $order->getTrackingNumber(),
            'trackingCarrier' => $order->getTrackingCarrier(),
            'trackingUrl' => $order->getTrackingUrl(),
            'dispatchedAt' => $order->getDispatchedAt()?->format('c'),
            'dispatchDocuments' => array_map(fn($doc) => [
                'id' => $doc->getId()->toRfc4122(),
                'filename' => $doc->getFilename(),
                'originalFilename' => $doc->getOriginalFilename(),
                'mimeType' => $doc->getMimeType(),
                'documentType' => $doc->getDocumentType(),
                'fileSize' => $doc->getFileSize(),
                'createdAt' => $doc->getCreatedAt()?->format('c')
            ], $savedDocuments),
            'message' => 'Order dispatched successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Get the upload path for order documents
     */
    private function getUploadPath(Order $order): string
    {
        return $this->uploadsDir . '/dispatch/' . $order->getId()->toRfc4122();
    }

    /**
     * Determine document type from filename and mime type
     */
    private function determineDocumentType(string $filename, string $mimeType): string
    {
        $lowerFilename = strtolower($filename);

        // Check for invoice-related keywords
        if (str_contains($lowerFilename, 'invoice') ||
            str_contains($lowerFilename, 'rechnung') ||
            str_contains($lowerFilename, 'faktura')) {
            return DispatchDocument::TYPE_INVOICE;
        }

        // Check for shipping/DHL sheet keywords
        if (str_contains($lowerFilename, 'shipping') ||
            str_contains($lowerFilename, 'dhl') ||
            str_contains($lowerFilename, 'delivery') ||
            str_contains($lowerFilename, 'lieferschein') ||
            str_contains($lowerFilename, 'versand') ||
            str_contains($lowerFilename, 'tracking')) {
            return DispatchDocument::TYPE_SHIPPING_SHEET;
        }

        return DispatchDocument::TYPE_OTHER;
    }
}
