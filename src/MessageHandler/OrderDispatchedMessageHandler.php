<?php

namespace App\MessageHandler;

use App\Message\OrderDispatchedMessage;
use App\Entity\Order;
use App\Entity\DispatchDocument;
use App\Repository\OrderRepository;
use App\Repository\DispatchDocumentRepository;
use App\Service\PriceCalculator;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Twig\Environment;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class OrderDispatchedMessageHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly DispatchDocumentRepository $documentRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly PriceCalculator $priceCalculator,
        private readonly string $uploadsDir,
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com'
    ) {
    }

    public function __invoke(OrderDispatchedMessage $message): void
    {
        $orderId = $message->getOrderId();

        $this->logger->info('Handling OrderDispatchedMessage', [
            'order_id' => $orderId->toRfc4122(),
            'document_ids' => $message->getDocumentIds()
        ]);

        try {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                $this->logger->error('Order not found in database', [
                    'order_id' => $orderId->toRfc4122()
                ]);
                return;
            }

            // Get dispatch documents
            $documents = $order->getDispatchDocuments()->toArray();

            // Send admin notification
            $this->sendAdminNotification($order, $documents);

            // Send TWO customer emails
            // Email 1: Invoice/Order Details email
            $this->sendCustomerInvoiceEmail($order);

            // Email 2: Documents email with attachments
            if (!empty($documents)) {
                $this->sendCustomerDocumentsEmail($order, $documents);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in OrderDispatchedMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function sendAdminNotification(Order $order, array $documents): void
    {
        try {
            $subject = 'Order #' . $order->getOrderNumber() . ' has been dispatched';

            $items = $this->priceCalculator->getOrderItemsDetails($order);
            $totalAmount = $this->priceCalculator->calculateOrderTotal($order);

            $templateParams = [
                'order' => $order,
                'items' => $items,
                'totalAmount' => $totalAmount,
                'documents' => $documents,
                'documentsCount' => count($documents),
                'base_url' => $this->getBaseUrl(),
            ];

            $htmlContent = $this->twig->render('emails/admin/order_dispatched.html.twig', $templateParams);

            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Order System'))
                ->to(new Address($this->adminEmail, 'Admin'))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Admin dispatch notification sent', [
                'order_number' => $order->getOrderNumber()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin dispatch notification', [
                'error' => $e->getMessage(),
                'order_id' => $order->getId()->toRfc4122()
            ]);
        }
    }

    /**
     * Send the Invoice/Order Details email to customer
     */
    private function sendCustomerInvoiceEmail(Order $order): void
    {
        try {
            $user = $order->getUser();

            if (!$user || !$user->getEmail()) {
                $this->logger->warning('Cannot send customer invoice email: no user or email', [
                    'order_id' => $order->getId()->toRfc4122()
                ]);
                return;
            }

            $customerEmail = $user->getEmail();
            $customerName = $user->getFullName();

            // Subject with "Invoice" prefix as per requirements
            $subject = 'Invoice - Your Deckard Order #' . $order->getOrderNumber() . ' has Been Dispatched';

            $items = $this->priceCalculator->getOrderItemsDetails($order);
            $totalAmount = $this->priceCalculator->calculateOrderTotal($order);

            $templateParams = [
                'order' => $order,
                'user' => $user,
                'items' => $items,
                'totalAmount' => $totalAmount,
                'base_url' => $this->getBaseUrl(),
                'supportEmail' => $_ENV['SUPPORT_EMAIL'] ?? 'support@deckard.com',
                'supportPhone' => $_ENV['SUPPORT_PHONE'] ?? '+43 1 234 5678',
            ];

            $htmlContent = $this->twig->render('emails/customer/order_dispatched.html.twig', $templateParams);

            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Deckard Orders'))
                ->to(new Address($customerEmail, $customerName))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Customer invoice email sent', [
                'customer_email' => $customerEmail,
                'order_number' => $order->getOrderNumber()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send customer invoice email', [
                'error' => $e->getMessage(),
                'order_id' => $order->getId()->toRfc4122()
            ]);
        }
    }

    /**
     * Send the Documents email with attachments to customer
     */
    private function sendCustomerDocumentsEmail(Order $order, array $documents): void
    {
        try {
            $user = $order->getUser();

            if (!$user || !$user->getEmail()) {
                $this->logger->warning('Cannot send customer documents email: no user or email', [
                    'order_id' => $order->getId()->toRfc4122()
                ]);
                return;
            }

            $customerEmail = $user->getEmail();
            $customerName = $user->getFullName();

            $subject = 'Shipping Documents - Order #' . $order->getOrderNumber();

            $templateParams = [
                'order' => $order,
                'user' => $user,
                'documents' => $documents,
                'documentsCount' => count($documents),
                'base_url' => $this->getBaseUrl(),
                'supportEmail' => $_ENV['SUPPORT_EMAIL'] ?? 'support@deckard.com',
            ];

            $htmlContent = $this->twig->render('emails/customer/order_dispatched_documents.html.twig', $templateParams);

            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Deckard Orders'))
                ->to(new Address($customerEmail, $customerName))
                ->subject($subject)
                ->html($htmlContent);

            // Attach documents
            foreach ($documents as $document) {
                /** @var DispatchDocument $document */
                $filePath = $this->uploadsDir . '/' . $document->getFilePath();

                if (file_exists($filePath)) {
                    $email->attachFromPath(
                        $filePath,
                        $document->getOriginalFilename(),
                        $document->getMimeType()
                    );

                    $this->logger->info('Attached document to email', [
                        'filename' => $document->getOriginalFilename(),
                        'type' => $document->getDocumentType()
                    ]);
                } else {
                    $this->logger->warning('Document file not found for attachment', [
                        'document_id' => $document->getId()->toRfc4122(),
                        'file_path' => $filePath
                    ]);
                }
            }

            $this->mailer->send($email);

            $this->logger->info('Customer documents email sent', [
                'customer_email' => $customerEmail,
                'order_number' => $order->getOrderNumber(),
                'attachments_count' => count($documents)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send customer documents email', [
                'error' => $e->getMessage(),
                'order_id' => $order->getId()->toRfc4122()
            ]);
        }
    }

    private function getBaseUrl(): string
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
            return $protocol . $_SERVER['HTTP_HOST'];
        }

        return $_ENV['APP_BASE_URL'] ?? 'https://example.com';
    }
}
