<?php

namespace App\MessageHandler;

use App\Message\OrderStatusChangedMessage;
use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\PriceCalculator;
use App\Service\OrderLogService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsMessageHandler]
class OrderStatusChangedMessageHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment     $twig,
        private readonly LoggerInterface $logger,
        private readonly PriceCalculator $priceCalculator,
        private readonly OrderLogService $orderLogService,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com'
    ) {
    }

    public function __invoke(OrderStatusChangedMessage $message): void
    {
        $orderId = $message->getOrderId();
        $newStatus = $message->getNewStatus();
        $previousStatus = $message->getOldStatus();
        $modifiedBy = $message->getModifiedBy();

        $this->logger->info('Handling OrderStatusChangedMessage', [
            'order_id' => $orderId->toRfc4122(),
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'modified_by' => $modifiedBy
        ]);

        try {
            $order = $this->orderRepository->find($orderId);

            if (!$order) {
                $this->logger->error('Order not found in database', [
                    'order_id' => $orderId->toRfc4122()
                ]);
                return;
            }

            // Create description with user information
            $description = sprintf(
                'Status changed by %s via API',
                $message->getModifiedByFullName() ?? 'Unknown User'
            );

            // Log the status change with user information
            $log = $this->orderLogService->logStatusChange(
                $order,
                $previousStatus,
                $newStatus,
                $description,
                [
                    'message_id' => uniqid('msg_'),
                    'processed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'handler' => self::class,
                    'modified_by' => $modifiedBy
                ]
            );

            // Persist the log if created
            if ($log !== null) {
                $this->entityManager->flush();

                $this->logger->info('Order status change logged', [
                    'order_id' => $orderId->toRfc4122(),
                    'log_id' => $log->getId()->toRfc4122(),
                    'transition' => $log->getTransitionDescription(),
                    'modified_by_name' => $message->getModifiedByFullName()
                ]);
            }

            // Don't send notifications for draft status
            if ($newStatus === Order::STATUS_DRAFT) {
                $this->logger->info('Skipping notifications for draft status', [
                    'order_id' => $orderId->toRfc4122()
                ]);
                return;
            }

            // Send admin notification with user info
            $this->sendAdminNotification($order, $newStatus, $previousStatus, $modifiedBy);

            // Send customer notification
            $this->sendCustomerNotification($order, $newStatus, $previousStatus);

        } catch (\Exception $e) {
            $this->logger->error('Error in OrderStatusChangedMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to let the message bus handle retry logic
            throw $e;
        }
    }

    private function sendAdminNotification(Order $order, string $newStatus, string $previousStatus, ?array $modifiedBy): void
    {
        try {
            // Determine which template to use based on the new status
            $templateName = match ($newStatus) {
                Order::STATUS_SUBMITTED => 'emails/admin/order_submitted.html.twig',
                Order::STATUS_CONFIRMED => 'emails/admin/order_confirmed.html.twig',
                Order::STATUS_DISPATCHED => 'emails/admin/order_dispatched.html.twig',
                Order::STATUS_COMPLETED => 'emails/admin/order_completed.html.twig',
                Order::STATUS_CANCELED => 'emails/admin/order_canceled.html.twig',
                default => 'emails/admin/order_status_changed.html.twig',
            };

            // Get the appropriate subject based on status
            $subject = match ($newStatus) {
                Order::STATUS_SUBMITTED => 'New Order Submitted: #' . $order->getOrderNumber(),
                Order::STATUS_CONFIRMED => 'Order #' . $order->getOrderNumber() . ' has been confirmed',
                Order::STATUS_DISPATCHED => 'Order #' . $order->getOrderNumber() . ' has been dispatched',
                Order::STATUS_COMPLETED => 'Order #' . $order->getOrderNumber() . ' has been completed',
                Order::STATUS_CANCELED => 'Order #' . $order->getOrderNumber() . ' has been canceled',
                default => 'Order #' . $order->getOrderNumber() . ' status changed to ' . $newStatus,
            };

            $this->logger->info('Preparing to send admin email notification', [
                'template' => $templateName,
                'subject' => $subject,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'modified_by' => $modifiedBy
            ]);

            // Check if template exists
            if (!$this->twig->getLoader()->exists($templateName)) {
                $this->logger->warning('Template not found, falling back to generic template', [
                    'missing_template' => $templateName
                ]);
                $templateName = 'emails/admin/order_status_changed.html.twig';

                // Check if the fallback template exists
                if (!$this->twig->getLoader()->exists($templateName)) {
                    throw new \Exception('Email template not found: ' . $templateName);
                }
            }

            // Calculate client-specific prices
            $items = $this->priceCalculator->getOrderItemsDetails($order);
            $totalAmount = $this->priceCalculator->calculateOrderTotal($order);

            // Get order history for context
            $orderHistory = $this->orderLogService->getOrderHistory($order);

            // Prepare template parameters
            $templateParams = [
                'order' => $order,
                'status' => $newStatus,
                'previousStatus' => $previousStatus,
                'items' => $items,
                'totalAmount' => $totalAmount,
                'orderHistory' => $orderHistory,
                'statusChangeCount' => count($orderHistory),
                'base_url' => $this->getBaseUrl(),
                'modifiedBy' => $modifiedBy,
                'modifiedByName' => $this->getModifiedByName($modifiedBy),
            ];

            // Render email content using Twig
            $htmlContent = $this->twig->render($templateName, $templateParams);

            // Create and send the email
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Order System'))
                ->to(new Address($this->adminEmail, 'Admin'))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Admin email notification sent', [
                'status' => $newStatus,
                'order_number' => $order->getOrderNumber(),
                'modified_by' => $this->getModifiedByName($modifiedBy)
            ]);

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

    private function sendCustomerNotification(Order $order, string $newStatus, string $previousStatus): void
    {
        try {
            $user = $order->getUser();

            if (!$user || !$user->getEmail()) {
                $this->logger->warning('Cannot send customer status notification: no user or email', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'order_number' => $order->getOrderNumber()
                ]);
                return;
            }

            $customerEmail = $user->getEmail();
            $customerName = $user->getFullName();

            // Get the appropriate subject based on status
            $subject = match ($newStatus) {
                Order::STATUS_SUBMITTED => 'Order Received: #' . $order->getOrderNumber(),
                Order::STATUS_CONFIRMED => 'Your Deckard Order #' . $order->getOrderNumber() . ' Confirmed',
                Order::STATUS_DISPATCHED => 'Your Deckard Order #' . $order->getOrderNumber() . ' has Been Dispatched',
                Order::STATUS_COMPLETED => 'Your Deckard Order #' . $order->getOrderNumber() . ' is Now Complete',
                Order::STATUS_CANCELED => 'Cancellation Confirmation for Deckard Order #' . $order->getOrderNumber(),
                default => 'Update on Your Order #' . $order->getOrderNumber(),
            };

            // Determine which template to use based on the new status
            $templateName = match ($newStatus) {
                Order::STATUS_SUBMITTED => 'emails/customer/order_confirmation.html.twig',
                Order::STATUS_CONFIRMED => 'emails/customer/order_confirmed.html.twig',
                Order::STATUS_DISPATCHED => 'emails/customer/order_dispatched.html.twig',
                Order::STATUS_COMPLETED => 'emails/customer/order_completed.html.twig',
                Order::STATUS_CANCELED => 'emails/customer/order_canceled.html.twig',
                default => 'emails/customer/order_status_changed.html.twig',
            };

            // Check if template exists, fall back to generic if not
            if (!$this->twig->getLoader()->exists($templateName)) {
                $templateName = 'emails/customer/order_status_changed.html.twig';
            }

            // Calculate client-specific prices
            $items = $this->priceCalculator->getOrderItemsDetails($order);
            $totalAmount = $this->priceCalculator->calculateOrderTotal($order);

            // Prepare template parameters
            $templateParams = [
                'order' => $order,
                'user' => $user,
                'status' => $newStatus,
                'previousStatus' => $previousStatus,
                'items' => $items,
                'totalAmount' => $totalAmount,
                'base_url' => $this->getBaseUrl(),
                'isFirstTimeCustomer' => $this->isFirstTimeCustomer($user),
                'supportEmail' => $_ENV['SUPPORT_EMAIL'] ?? 'support@deckard.com',
                'supportPhone' => $_ENV['SUPPORT_PHONE'] ?? '+43 1 234 5678',
            ];

            // Render email content using Twig
            $htmlContent = $this->twig->render($templateName, $templateParams);

            // Create and send the email
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Deckard Orders'))
                ->to(new Address($customerEmail, $customerName))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Customer status notification email sent', [
                'customer_email' => $customerEmail,
                'status' => $newStatus,
                'order_number' => $order->getOrderNumber()
            ]);

            // Log email sent in order history (only if not same status)
            if ($previousStatus !== $newStatus) {
                $emailLog = $this->orderLogService->logStatusChange(
                    $order,
                    $newStatus,
                    $newStatus, // Same status - just logging email sent
                    'Customer notification email sent',
                    [
                        'email_type' => 'status_notification',
                        'recipient' => $customerEmail,
                        'subject' => $subject,
                        'template' => $templateName
                    ]
                );

                if ($emailLog !== null) {
                    $this->entityManager->flush();
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to send customer status notification', [
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
