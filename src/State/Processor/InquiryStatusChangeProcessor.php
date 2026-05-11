<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inquiry;
use App\Entity\User;
use App\Message\InquiryStatusChangedMessage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

#[AsDecorator('api_platform.doctrine.orm.state.persist_processor', priority: 10)]
class InquiryStatusChangeProcessor implements ProcessorInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __construct(
        private ProcessorInterface $decorated,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private WorkflowInterface $inquiryStateMachine,
        private Security $security
    ) {
    }

    /**
     * @param Inquiry|object $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Check if this is an Inquiry update operation with workflow transitions
        if ($data instanceof Inquiry &&
            ($operation->getMethod() === 'PUT' || $operation->getMethod() === 'PATCH')) {

            $this->logger->info('Processing inquiry update', [
                'inquiry_id' => $data->getId()->toRfc4122(),
                'operation' => $operation->getMethod()
            ]);

            // Get the original entity from the database to compare status
            $originalInquiry = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);

            // If this is a new entity, there won't be original data
            if ($originalInquiry) {
                $oldStatus = $originalInquiry['status'] ?? null;
                $newStatus = $data->getStatus();

                $this->logger->info('Checking status change', [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'is_draft' => $data->isDraft()
                ]);

                // If status has changed, validate and apply workflow transition
                if ($oldStatus && $newStatus && $oldStatus !== $newStatus) {
                    // Handle cancellation fields
                    if ($newStatus === Inquiry::STATUS_CANCELED && $oldStatus !== Inquiry::STATUS_CANCELED) {
                        $this->validateAndSetCanceledFields($data);
                    }

                    try {
                        // Temporarily reset status to old status so workflow can track the transition
                        $data->setStatus($oldStatus);

                        // Determine which transition to apply based on status change
                        $transition = $this->determineTransition($oldStatus, $newStatus);

                        $this->logger->info('Attempting workflow transition', [
                            'transition' => $transition,
                            'can_apply' => $transition ? $this->inquiryStateMachine->can($data, $transition) : false,
                            'enabled_transitions' => array_map(
                                fn($t) => $t->getName(),
                                $this->inquiryStateMachine->getEnabledTransitions($data)
                            )
                        ]);

                        if ($transition && $this->inquiryStateMachine->can($data, $transition)) {
                            // Apply the workflow transition
                            $this->inquiryStateMachine->apply($data, $transition);

                            $this->logger->info('Workflow transition applied', [
                                'inquiry_id' => $data->getId()->toRfc4122(),
                                'transition' => $transition,
                                'old_status' => $oldStatus,
                                'new_status' => $newStatus,
                                'final_status' => $data->getStatus()
                            ]);
                        } else {
                            // Set status back to new status before throwing exception
                            $data->setStatus($newStatus);

                            // Get available transitions for better error message
                            $availableTransitions = array_map(
                                fn($t) => $t->getName(),
                                $this->inquiryStateMachine->getEnabledTransitions($data)
                            );

                            throw new TransitionException(
                                $data,
                                $transition ?? 'unknown',
                                $this->inquiryStateMachine,
                                sprintf(
                                    'Invalid status transition from "%s" to "%s" for inquiry %s. Available transitions: %s',
                                    $oldStatus,
                                    $newStatus,
                                    $data->getInquiryNumber(),
                                    implode(', ', $availableTransitions) ?: 'none'
                                )
                            );
                        }
                    } catch (TransitionException $e) {
                        $this->logger->error('Workflow transition failed', [
                            'inquiry_id' => $data->getId()->toRfc4122(),
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'error' => $e->getMessage()
                        ]);

                        throw $e;
                    }
                }
            }
        }

        // Call the decorated processor to handle the actual persistence
        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Determine which workflow transition to apply based on status change
     */
    private function determineTransition(string $oldStatus, string $newStatus): ?string
    {
        // Map status changes to workflow transitions
        $transitionMap = [
            Inquiry::STATUS_DRAFT => [
                Inquiry::STATUS_SUBMITTED => 'submit',
                Inquiry::STATUS_PENDING_APPROVAL => 'submit_for_approval',
            ],
            Inquiry::STATUS_PENDING_APPROVAL => [
                Inquiry::STATUS_SUBMITTED => 'approve',
                Inquiry::STATUS_CANCELED => 'cancel',
            ],
            Inquiry::STATUS_SUBMITTED => [
                Inquiry::STATUS_IN_REVIEW => 'review',
                Inquiry::STATUS_MORE_INFO => 'request_more_info',
                Inquiry::STATUS_IN_PROGRESS => 'start_progress',
                Inquiry::STATUS_CANCELED => 'cancel',
            ],
            Inquiry::STATUS_IN_REVIEW => [
                Inquiry::STATUS_MORE_INFO => 'request_more_info',
                Inquiry::STATUS_IN_PROGRESS => 'start_progress',
                Inquiry::STATUS_CANCELED => 'cancel',
            ],
            Inquiry::STATUS_MORE_INFO => [
                Inquiry::STATUS_INFORMATION_PROVIDED => 'provide_information',
                Inquiry::STATUS_CANCELED => 'cancel',
            ],
            Inquiry::STATUS_INFORMATION_PROVIDED => [
                Inquiry::STATUS_MORE_INFO => 'request_more_info',
                Inquiry::STATUS_IN_PROGRESS => 'start_progress',
                Inquiry::STATUS_CANCELED => 'cancel',
            ],
            Inquiry::STATUS_IN_PROGRESS => [
                Inquiry::STATUS_COMPLETED => 'complete',
                Inquiry::STATUS_CANCELED => 'cancel',
            ],
            Inquiry::STATUS_COMPLETED => [
                Inquiry::STATUS_ACCEPTED => 'accept',
            ],
        ];

        return $transitionMap[$oldStatus][$newStatus] ?? null;
    }

    /**
     * Validate and set fields when inquiry is canceled
     */
    private function validateAndSetCanceledFields(Inquiry $inquiry): void
    {
        $authenticatedUser = $this->security->getUser();

        // Validate cancellation reason is provided
        if (empty($inquiry->getCancellationReason())) {
            throw new BadRequestHttpException('Cancellation reason is required when canceling an inquiry.');
        }

        // Set canceled timestamp and user
        $inquiry->setCancelledAt(new \DateTime());
        if ($authenticatedUser instanceof User) {
            $inquiry->setCancelledBy($authenticatedUser);
        }

        $this->logger->info('Inquiry canceled', [
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'cancellation_reason' => $inquiry->getCancellationReason(),
            'cancelled_by' => $authenticatedUser instanceof User ? $authenticatedUser->getEmail() : null
        ]);
    }
}
