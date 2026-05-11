<?php

namespace App\MessageHandler;

use App\Message\OrderCreatedMessage;
use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\PriceCalculator;
use App\Service\OrderLogService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Doctrine\ORM\EntityManagerInterface;

#[AsMessageHandler]
class OrderCreatedMessageHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
        private readonly PriceCalculator $priceCalculator,
        private readonly OrderLogService $orderLogService,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com'
    ) {
    }

    public function __invoke(OrderCreatedMessage $message): void
    {
        $orderId = $message->getOrderId();
        $metadata = $message->getMetadata();
        $modifiedBy = $metadata['modifiedBy'] ?? null;

        $this->logger->info('Handling OrderCreatedMessage', [
            'order_id' => $orderId->toRfc4122(),
            'metadata' => $metadata
        ]);

        try {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                $this->logger->error('Order not found in database', [
                    'order_id' => $orderId->toRfc4122()
                ]);
                return;
            }

            $this->logger->info('Found order', [
                'order_number' => $order->getOrderNumber(),
                'order_status' => $order->getStatus(),
                'is_draft' => $order->isDraft(),
                'total_amount' => $order->getTotalAmount(),
                'items_count' => $order->getItems()->count(),
                'created_by' => $modifiedBy
            ]);

            // Skip notifications for draft orders
            if ($order->isDraft() || $order->getStatus() === Order::STATUS_DRAFT) {
                $this->logger->info('Skipping notifications for draft order', [
                    'order_id' => $orderId->toRfc4122()
                ]);
                return;
            }

            // Create description with user information
            $description = sprintf(
                'Order created and submitted by %s',
                $this->getModifiedByName($modifiedBy)
            );

            // Log order creation if it's not a draft
            if ($order->getStatus() === Order::STATUS_SUBMITTED) {
                $log = $this->orderLogService->logOrderSubmission(
                    $order,
                    $description,
                    [
                        'created_by' => $modifiedBy,
                        'operation' => $metadata['operation'] ?? 'create'
                    ]
                );

                if ($log !== null) {
                    $this->entityManager->flush();

                    $this->logger->info('Order creation logged', [
                        'order_id' => $orderId->toRfc4122(),
                        'log_id' => $log->getId()->toRfc4122(),
                        'created_by_name' => $this->getModifiedByName($modifiedBy)
                    ]);
                }
            }

            // Send email to admin with user info
            $this->sendAdminNotification($order, $modifiedBy);

            // Send email to the customer if there's a valid user with email
            $this->sendCustomerNotification($order);

        } catch (\Exception $e) {
            $this->logger->error('Error in OrderCreatedMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to let the message bus handle retry logic
            throw $e;
        }
    }

    private function sendAdminNotification(Order $order, ?array $modifiedBy): void
    {
        try {
            // Calculate client-specific prices
            $items = $this->priceCalculator->getOrderItemsDetails($order);
            $totalAmount = $this->priceCalculator->calculateOrderTotal($order);

            // Get order history if any exists
            $orderHistory = $this->orderLogService->getOrderHistory($order);

            // Create template parameters
            $templateParams = [
                'order' => $order,
                'items' => $items,
                'totalAmount' => $totalAmount,
                'orderHistory' => $orderHistory,
                'base_url' => $this->getBaseUrl(),
                'createdBy' => $modifiedBy,
                'createdByName' => $this->getModifiedByName($modifiedBy),
            ];

            // Render template
            $htmlContent = $this->twig->render('emails/admin/order_submitted.html.twig', $templateParams);

            // Create email for admin
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Order System'))
                ->to(new Address($this->adminEmail, 'Admin'))
                ->subject('New Order Received: #' . $order->getOrderNumber())
                ->html($htmlContent);

            $this->logger->info('Sending admin email notification', [
                'from' => $this->senderEmail,
                'to' => $this->adminEmail,
                'subject' => 'New Order Received: #' . $order->getOrderNumber(),
                'created_by' => $this->getModifiedByName($modifiedBy)
            ]);

            // Send the email
            $this->mailer->send($email);

            $this->logger->info('Admin email notification sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin email notification', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'order_id' => $order->getId()->toRfc4122()
            ]);

            // Don't re-throw - email failure shouldn't break the process
        }
    }

    private function sendCustomerNotification(Order $order): void
    {
        try {
            $user = $order->getUser();

            if (!$user || !$user->getEmail()) {
                $this->logger->warning('Cannot send customer notification: no user or email', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'order_number' => $order->getOrderNumber()
                ]);
                return;
            }

            $customerEmail = $user->getEmail();
            $customerName = $user->getFullName();

            // Calculate client-specific prices
            $items = $this->priceCalculator->getOrderItemsDetails($order);
            $totalAmount = $this->priceCalculator->calculateOrderTotal($order);

            // Prepare template parameters
            $templateParams = [
                'order' => $order,
                'user' => $user,
                'items' => $items,
                'totalAmount' => $totalAmount,
                'base_url' => $this->getBaseUrl(),
                'isFirstTimeCustomer' => $this->isFirstTimeCustomer($user),
                'supportEmail' => $_ENV['SUPPORT_EMAIL'] ?? 'support@deckard.com',
                'supportPhone' => $_ENV['SUPPORT_PHONE'] ?? '+43 1 234 5678',
            ];

            // Render template
            $htmlContent = $this->twig->render('emails/customer/order_confirmation.html.twig', $templateParams);

            // Create email for customer
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Deckard Orders'))
                ->to(new Address($customerEmail, $customerName))
                ->subject('Your Deckard Order #' . $order->getOrderNumber() . ' Confirmation')
                ->html($htmlContent);

            $this->logger->info('Sending customer email notification', [
                'from' => $this->senderEmail,
                'to' => $customerEmail,
                'subject' => 'Your Deckard Order #' . $order->getOrderNumber() . ' Confirmation'
            ]);

            // Send the email
            $this->mailer->send($email);

            $this->logger->info('Customer email notification sent successfully');

            // Log email sent in order history
            $this->orderLogService->logStatusChange(
                $order,
                $order->getStatus(),
                $order->getStatus(), // Same status - just logging email sent
                'Customer confirmation email sent',
                [
                    'email_type' => 'order_confirmation',
                    'recipient' => $customerEmail,
                    'subject' => 'Your Deckard Order #' . $order->getOrderNumber() . ' Confirmation'
                ]
            );
            $this->entityManager->flush();

        } catch (\Exception $e) {
            $this->logger->error('Failed to send customer email notification', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'order_id' => $order->getId()->toRfc4122()
            ]);

            // Don't re-throw - email failure shouldn't break the process
        }
    }

    /**
     * Get the base URL for email links
     */
    private function getBaseUrl(): string
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
            return $protocol . $_SERVER['HTTP_HOST'];
        }

        // Fallback - should be configured in environment variables
        return $_ENV['APP_BASE_URL'] ?? 'https://example.com';
    }

    /**
     * Check if this is the customer's first order
     */
    private function isFirstTimeCustomer($user): bool
    {
        $orderCount = $this->orderRepository->count([
            'user' => $user,
            'status' => [
                Order::STATUS_COMPLETED,
                Order::STATUS_DISPATCHED,
                Order::STATUS_CONFIRMED
            ]
        ]);

        return $orderCount === 0;
    }

    /**
     * Get formatted name from modifiedBy array
     */
    private function getModifiedByName(?array $modifiedBy): string
    {
        if (!$modifiedBy) {
            return 'System';
        }

        return $modifiedBy['fullName'] ??
            ($modifiedBy['firstName'] . ' ' . $modifiedBy['lastName']) ??
            $modifiedBy['email'] ??
            'Unknown User';
    }
}
