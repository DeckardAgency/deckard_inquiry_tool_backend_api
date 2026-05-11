<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Exception\TransitionException;

/**
 * Handles Order status changes through Symfony Workflow
 */
class OrderStatusChangeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private WorkflowInterface $orderStateMachine
    ) {
    }

    /**
     * @param Order|object $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Check if this is an Order update operation with workflow transitions
        if ($data instanceof Order &&
            ($operation->getMethod() === 'PUT' || $operation->getMethod() === 'PATCH')) {

            $this->logger->info('Processing order update', [
                'order_id' => $data->getId()->toRfc4122(),
                'operation' => $operation->getMethod()
            ]);

            // Get the original entity from the database to compare status
            $originalOrder = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);

            // If this is a new entity, there won't be original data
            if ($originalOrder) {
                $oldStatus = $originalOrder['status'] ?? null;
                $newStatus = $data->getStatus();

                $this->logger->info('Checking status change', [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'is_draft' => $data->isDraft()
                ]);

                // If status has changed, validate and apply workflow transition
                if ($oldStatus && $newStatus && $oldStatus !== $newStatus) {
                    try {
                        // Determine which transition to apply based on status change
                        $transition = $this->determineTransition($oldStatus, $newStatus);

                        if ($transition && $this->orderStateMachine->can($data, $transition)) {
                            // Apply the workflow transition
                            $this->orderStateMachine->apply($data, $transition);

                            $this->logger->info('Workflow transition applied', [
                                'order_id' => $data->getId()->toRfc4122(),
                                'transition' => $transition,
                                'old_status' => $oldStatus,
                                'new_status' => $newStatus
                            ]);
                        } else {
                            // Invalid transition - throw exception
                            throw new TransitionException(
                                $data,
                                $transition ?? 'unknown',
                                $this->orderStateMachine,
                                sprintf(
                                    'Invalid status transition from "%s" to "%s" for order %s',
                                    $oldStatus,
                                    $newStatus,
                                    $data->getOrderNumber()
                                )
                            );
                        }
                    } catch (TransitionException $e) {
                        $this->logger->error('Workflow transition failed', [
                            'order_id' => $data->getId()->toRfc4122(),
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'error' => $e->getMessage()
                        ]);

                        throw $e;
                    }
                }
            }
        }

        // Call the persist processor to handle the actual persistence
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Determine which workflow transition to apply based on status change
     */
    private function determineTransition(string $oldStatus, string $newStatus): ?string
    {
        // Map status changes to workflow transitions
        $transitionMap = [
            Order::STATUS_DRAFT => [
                Order::STATUS_SUBMITTED => 'submit',
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
}
