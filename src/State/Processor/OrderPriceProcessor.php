<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Message\OrderCreatedMessage;
use App\Message\OrderStatusChangedMessage;
use App\Security\Voter\AgentOrderVoter;
use App\Service\PriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Custom processor that ensures client-specific prices are applied to order items
 * and handles order creation workflow with Symfony Workflow component
 */
final class OrderPriceProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private PriceCalculator $priceCalculator,
        private LoggerInterface $logger,
        private Security $security,
        private MessageBusInterface $messageBus,
        private WorkflowInterface $orderStateMachine
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Only process Order entities
        if (!$data instanceof Order) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Get the authenticated user who is making this request
        $authenticatedUser = $this->security->getUser();
        $modifiedBy = null;

        if ($authenticatedUser instanceof User) {
            $modifiedBy = [
                'id' => $authenticatedUser->getId()->toRfc4122(),
                'email' => $authenticatedUser->getEmail(),
                'firstName' => $authenticatedUser->getFirstName(),
                'lastName' => $authenticatedUser->getLastName(),
                'fullName' => $authenticatedUser->getFullName()
            ];
        }

        // Check if this is a draft submission or approve operation
        $isDraftSubmission = str_contains($operation->getUriTemplate() ?? '', '/submit');
        $isApproveOperation = str_contains($operation->getUriTemplate() ?? '', '/approve');

        // Capture the original status before processing
        $originalOrder = null;
        $isNewOrder = false;

        if ($data->getId()) {
            $uow = $this->entityManager->getUnitOfWork();
            $originalOrder = $uow->getOriginalEntityData($data);

            // If no original data, try to fetch from database
            if (!$originalOrder) {
                $existingOrder = $this->entityManager->getRepository(Order::class)->find($data->getId());
                if ($existingOrder) {
                    $originalOrder = [
                        'status' => $existingOrder->getStatus(),
                        'isDraft' => $existingOrder->isDraft()
                    ];
                }
            }
        } else {
            $isNewOrder = true;
        }

        $this->logger->info('Processing order for price calculation', [
            'order_id' => $data->getId()?->toRfc4122(),
            'order_number' => $data->getOrderNumber(),
            'user' => $data->getUser()?->getEmail(),
            'items_count' => $data->getItems()->count(),
            'operation' => $operation->getName(),
            'is_new_order' => $isNewOrder,
            'current_status' => $data->getStatus(),
            'original_status' => $originalOrder['status'] ?? null,
            'modified_by' => $modifiedBy
        ]);

        // Set the current user if it's a new Order and no user is set
        if ($operation->getMethod() === 'POST' && $data->getUser() === null) {
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $data->setUser($user);
            }
        }

        // Handle onBehalfOfClient: validate for agents, clear for non-agents
        if ($data->getOnBehalfOfClient() !== null && $authenticatedUser instanceof User) {
            if (in_array('ROLE_USER_CLIENT_AGENT', $authenticatedUser->getRoles(), true)) {
                AgentOrderVoter::validateAgentAuthorization($authenticatedUser, $data->getOnBehalfOfClient());
            } elseif (!in_array('ROLE_ADMIN', $authenticatedUser->getRoles(), true)) {
                $data->setOnBehalfOfClient(null);
            }
        }

        // Validate agent authorization for per-item clients (only for agents)
        if ($authenticatedUser instanceof User && in_array('ROLE_USER_CLIENT_AGENT', $authenticatedUser->getRoles(), true)) {
            AgentOrderVoter::validateAgentItemAuthorization($authenticatedUser, $data);
        }

        // Intercept direct POST submissions when approval is required
        // Skip for /submit (handled by OrderDraftProcessor) and /approve (already approved)
        if ($operation->getMethod() === 'POST'
            && $data->getStatus() === Order::STATUS_SUBMITTED
            && !$isDraftSubmission
            && !$isApproveOperation
        ) {
            $postUser = $data->getUser();
            // For agent orders, check the target client's approval setting
            $approvalClient = $data->getOnBehalfOfClient() ?? $postUser?->getClient();
            if ($postUser instanceof User
                && $approvalClient
                && $approvalClient->getRequiresOrderApproval()
                && !in_array('ROLE_CLIENT_ADMIN', $postUser->getRoles(), true)
                && !in_array('ROLE_ADMIN', $postUser->getRoles(), true)
            ) {
                $data->setStatus(Order::STATUS_PENDING_APPROVAL);
                $data->setIsDraft(false);
            }
        }

        // Ensure all order items have the correct client-specific prices
        $this->updateOrderItemPrices($data);

        // Recalculate order total
        $data->calculateTotalAmount();

        $this->logger->info('Order prices calculated', [
            'order_id' => $data->getId()?->toRfc4122(),
            'total_amount' => $data->getTotalAmount()
        ]);

        // Handle workflow transitions for status changes
        if ($originalOrder && !$isNewOrder) {
            $oldStatus = $originalOrder['status'] ?? null;
            $newStatus = $data->getStatus();

            // Validate and set fields for dispatched status
            if ($newStatus === Order::STATUS_DISPATCHED && $oldStatus !== Order::STATUS_DISPATCHED) {
                $this->validateAndSetDispatchedFields($data, $authenticatedUser);
            }

            // Validate and set fields for canceled status
            if ($newStatus === Order::STATUS_CANCELED && $oldStatus !== Order::STATUS_CANCELED) {
                $this->validateAndSetCanceledFields($data, $authenticatedUser);
            }

            $this->handleWorkflowTransition($data, $originalOrder);
        }

        // Process with the standard persist processor
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Handle message dispatching after persistence
        $this->handleMessageDispatching($data, $operation, $isNewOrder, $originalOrder, $isDraftSubmission, $modifiedBy);

        return $result;
    }

    /**
     * Handle message dispatching based on the operation and order state
     */
    private function handleMessageDispatching(
        Order $order,
        Operation $operation,
        bool $isNewOrder,
        ?array $originalOrder,
        bool $isDraftSubmission,
        ?array $modifiedBy
    ): void
    {
        // Skip all message dispatching for draft orders
        if ($order->isDraft()) {
            $this->logger->info('Skipping message dispatch for draft order', [
                'order_id' => $order->getId()->toRfc4122(),
                'status' => $order->getStatus()
            ]);
            return;
        }

        // Skip message dispatching for orders pending approval (no notifications to Deckard yet)
        if ($order->getStatus() === Order::STATUS_PENDING_APPROVAL) {
            $this->logger->info('Skipping message dispatch for pending approval order', [
                'order_id' => $order->getId()->toRfc4122(),
                'status' => $order->getStatus()
            ]);
            return;
        }

        // For draft submission, always dispatch OrderCreatedMessage
        if ($isDraftSubmission && !$order->isDraft()) {
            try {
                $this->logger->info('Dispatching OrderCreatedMessage for draft submission', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'status' => $order->getStatus(),
                    'modified_by' => $modifiedBy
                ]);

                $message = new OrderCreatedMessage($order->getId());
                $message->addMetadata('modifiedBy', $modifiedBy);
                $message->addMetadata('operation', 'draft_submission');

                $this->messageBus->dispatch($message);
                return; // Exit early to prevent duplicate messages
            } catch (\Exception $e) {
                $this->logger->error('Failed to dispatch OrderCreatedMessage for draft submission', [
                    'error' => $e->getMessage()
                ]);
            }
            return;
        }

        // For new orders that are not drafts, dispatch OrderCreatedMessage
        if ($isNewOrder && $operation->getMethod() === 'POST') {
            try {
                $this->logger->info('Dispatching OrderCreatedMessage for new order', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'status' => $order->getStatus(),
                    'modified_by' => $modifiedBy
                ]);

                $message = new OrderCreatedMessage($order->getId());
                $message->addMetadata('modifiedBy', $modifiedBy);
                $message->addMetadata('operation', 'create');

                $this->messageBus->dispatch($message);
            } catch (\Exception $e) {
                $this->logger->error('Failed to dispatch OrderCreatedMessage', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // NOTE: For existing orders, status change messages are now handled by OrderWorkflowSubscriber
        // The workflow.order.completed event triggers OrderStatusChangedMessage dispatch
        // No additional dispatching needed here to avoid duplicate emails
    }

    /**
     * Update all order item prices based on client-specific pricing
     */
    private function updateOrderItemPrices(Order $order): void
    {
        $user = $order->getUser();
        // Order-level and user-level client for fallback
        $orderLevelClient = $order->getOnBehalfOfClient();
        $userClient = $user?->getClient();

        foreach ($order->getItems() as $item) {
            /** @var OrderItem $item */
            // Per-item client takes priority, then order-level, then user's own client
            $client = $item->getOnBehalfOfClient() ?? $orderLevelClient ?? $userClient;
            $product = $item->getProduct();

            if (!$product) {
                $this->logger->warning('Order item without product', [
                    'item_id' => $item->getId()?->toRfc4122()
                ]);
                continue;
            }

            // Ensure the item has the order reference set
            if ($item->getOrderRef() === null) {
                $item->setOrderRef($order);
            }

            // Get the appropriate price
            $price = $product->getPrice(); // Default price
            $isCustomPrice = false;

            if ($client) {
                // Use PriceCalculator service to get client price
                $clientProductPrice = $this->priceCalculator->getClientProductPrice($client, $product);

                if ($clientProductPrice && $clientProductPrice->isValid()) {
                    $price = $clientProductPrice->getEffectivePrice();
                    $isCustomPrice = true;

                    $this->logger->debug('Applying client-specific price', [
                        'client' => $client->getCode(),
                        'product' => $product->getName(),
                        'standard_price' => $product->getPrice(),
                        'client_price' => $price,
                        'discount_percentage' => $clientProductPrice->getDiscountPercentage()
                    ]);
                }
            }

            // Update the item with the correct price
            $item->setUnitPrice($price);
            $item->setIsCustomPrice($isCustomPrice);

            // Recalculate subtotal
            $item->setQuantity($item->getQuantity()); // This triggers subtotal recalculation
        }
    }

    /**
     * Handle workflow transitions when status changes
     */
    private function handleWorkflowTransition(Order $order, array $originalOrder): void
    {
        $oldStatus = $originalOrder['status'] ?? null;
        $newStatus = $order->getStatus();

        // If status hasn't changed, nothing to do
        if ($oldStatus === $newStatus) {
            return;
        }

        $this->logger->info('Handling workflow transition for order', [
            'order_id' => $order->getId()->toRfc4122(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'current_marking' => $this->orderStateMachine->getMarking($order)->getPlaces()
        ]);

        try {
            // Temporarily reset status to old status so workflow can track the transition
            $order->setStatus($oldStatus);

            // Determine which transition to apply
            $transition = $this->determineOrderTransition($oldStatus, $newStatus);

            $this->logger->info('Attempting workflow transition', [
                'transition' => $transition,
                'can_apply' => $transition ? $this->orderStateMachine->can($order, $transition) : false,
                'enabled_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->orderStateMachine->getEnabledTransitions($order)
                )
            ]);

            if ($transition && $this->orderStateMachine->can($order, $transition)) {
                // Apply the workflow transition
                $this->orderStateMachine->apply($order, $transition);

                $this->logger->info('Workflow transition applied successfully', [
                    'order_id' => $order->getId()->toRfc4122(),
                    'transition' => $transition,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'final_status' => $order->getStatus()
                ]);
            } else {
                // Set status back to new status before throwing exception
                $order->setStatus($newStatus);

                // Get available transitions for better error message
                $availableTransitions = array_map(
                    fn($t) => $t->getName(),
                    $this->orderStateMachine->getEnabledTransitions($order)
                );

                throw new TransitionException(
                    $order,
                    $transition ?? 'unknown',
                    $this->orderStateMachine,
                    sprintf(
                        'Invalid status transition from "%s" to "%s" for order %s. Available transitions: %s',
                        $oldStatus,
                        $newStatus,
                        $order->getOrderNumber(),
                        implode(', ', $availableTransitions) ?: 'none'
                    )
                );
            }
        } catch (TransitionException $e) {
            $this->logger->error('Workflow transition failed', [
                'order_id' => $order->getId()->toRfc4122(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Determine which workflow transition to apply based on status change
     */
    private function determineOrderTransition(string $oldStatus, string $newStatus): ?string
    {
        // Map status changes to workflow transitions
        $transitionMap = [
            Order::STATUS_DRAFT => [
                Order::STATUS_SUBMITTED => 'submit',
                Order::STATUS_PENDING_APPROVAL => 'submit_for_approval',
            ],
            Order::STATUS_PENDING_APPROVAL => [
                Order::STATUS_SUBMITTED => 'approve',
                Order::STATUS_CANCELED => 'cancel',
            ],
            Order::STATUS_SUBMITTED => [
                Order::STATUS_CONFIRMED => 'confirm',
                Order::STATUS_CANCELED => 'cancel',
            ],
            Order::STATUS_CONFIRMED => [
                Order::STATUS_DISPATCHED => 'dispatch',
                Order::STATUS_CANCELED => 'cancel',
            ],
            Order::STATUS_DISPATCHED => [
                Order::STATUS_COMPLETED => 'complete',
            ],
        ];

        return $transitionMap[$oldStatus][$newStatus] ?? null;
    }

    /**
     * Validate and set fields when order is dispatched
     */
    private function validateAndSetDispatchedFields(Order $order, ?User $authenticatedUser): void
    {
        // Validate required tracking fields
        if (empty($order->getTrackingNumber())) {
            throw new BadRequestHttpException('Tracking number is required when dispatching an order.');
        }

        if (empty($order->getTrackingCarrier())) {
            throw new BadRequestHttpException('Tracking carrier is required when dispatching an order.');
        }

        // Validate carrier is one of the allowed values
        $allowedCarriers = ['dhl', 'ups', 'fedex', 'dpd', 'gls', 'other'];
        if (!in_array(strtolower($order->getTrackingCarrier()), $allowedCarriers)) {
            throw new BadRequestHttpException(
                sprintf('Invalid tracking carrier. Allowed values: %s', implode(', ', $allowedCarriers))
            );
        }

        // Auto-generate tracking URL if not provided
        if (empty($order->getTrackingUrl())) {
            $generatedUrl = $order->generateTrackingUrl();
            if ($generatedUrl) {
                $order->setTrackingUrl($generatedUrl);
            }
        }

        // Set dispatched timestamp and user
        $order->setDispatchedAt(new \DateTime());
        if ($authenticatedUser instanceof User) {
            $order->setDispatchedBy($authenticatedUser);
        }

        $this->logger->info('Order dispatched with tracking info', [
            'order_id' => $order->getId()->toRfc4122(),
            'tracking_number' => $order->getTrackingNumber(),
            'tracking_carrier' => $order->getTrackingCarrier(),
            'tracking_url' => $order->getTrackingUrl(),
            'dispatched_by' => $authenticatedUser?->getEmail()
        ]);
    }

    /**
     * Validate and set fields when order is canceled
     */
    private function validateAndSetCanceledFields(Order $order, ?User $authenticatedUser): void
    {
        // Validate cancellation reason is provided
        if (empty($order->getCancellationReason())) {
            throw new BadRequestHttpException('Cancellation reason is required when canceling an order.');
        }

        // Set canceled timestamp and user
        $order->setCancelledAt(new \DateTime());
        if ($authenticatedUser instanceof User) {
            $order->setCancelledBy($authenticatedUser);
        }

        $this->logger->info('Order canceled', [
            'order_id' => $order->getId()->toRfc4122(),
            'cancellation_reason' => $order->getCancellationReason(),
            'cancelled_by' => $authenticatedUser?->getEmail()
        ]);
    }
}
