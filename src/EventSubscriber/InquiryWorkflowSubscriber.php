<?php

namespace App\EventSubscriber;

use App\Entity\Inquiry;
use App\Entity\User;
use App\Message\InquiryStatusChangedMessage;
use App\Service\InquiryLogService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;

class InquiryWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
        private readonly InquiryLogService $inquiryLogService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.inquiry.completed' => 'onCompleted',
            'workflow.inquiry.guard' => 'onGuard',
        ];
    }

    /**
     * Called when a workflow transition is completed
     */
    public function onCompleted(CompletedEvent $event): void
    {
        /** @var Inquiry $inquiry */
        $inquiry = $event->getSubject();

        // Skip draft inquiries
        if ($inquiry->isDraft()) {
            return;
        }

        $transition = $event->getTransition();
        $marking = $event->getMarking();
        $places = $marking->getPlaces();
        $newStatus = array_key_first($places);

        // Skip notifications for pending_approval transitions (internal to client)
        if ($newStatus === Inquiry::STATUS_PENDING_APPROVAL) {
            $this->logger->info('Skipping notification for pending approval inquiry', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'transition' => $transition->getName()
            ]);
            return;
        }

        // Get the old status from the transition's "from" places
        $fromPlaces = $transition->getFroms();
        $oldStatus = reset($fromPlaces) ?: 'unknown'; // Get first "from" place

        // Get current authenticated user
        $user = $this->security->getUser();
        $modifiedBy = null;
        if ($user instanceof User) {
            $modifiedBy = [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ];
        }

        $this->logger->info('Inquiry workflow transition completed', [
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'inquiry_number' => $inquiry->getInquiryNumber(),
            'transition' => $transition->getName(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'workflow' => $event->getWorkflowName(),
            'modified_by' => $modifiedBy
        ]);

        // Create description with user information
        $description = sprintf(
            'Status changed by %s via workflow',
            $modifiedBy['fullName'] ?? 'Unknown User'
        );

        // Log the status change with user information
        $log = $this->inquiryLogService->logStatusChange(
            $inquiry,
            $oldStatus,
            $newStatus,
            $description,
            [
                'message_id' => uniqid('msg_'),
                'processed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'handler' => self::class,
                'modified_by' => $modifiedBy,
                'transition' => $transition->getName()
            ]
        );

        // Persist the log if created
        if ($log !== null) {
            $this->entityManager->flush();

            $this->logger->info('Inquiry status change logged', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'log_id' => $log->getId()->toRfc4122(),
                'transition' => $log->getTransitionDescription(),
                'modified_by_name' => $modifiedBy['fullName'] ?? 'Unknown'
            ]);
        }

        // Dispatch status changed message for email notifications
        $message = new InquiryStatusChangedMessage(
            $inquiry->getId(),
            $oldStatus,  // old status from transition
            $newStatus   // new status
        );

        $this->messageBus->dispatch($message);

        $this->logger->info('InquiryStatusChangedMessage dispatched', [
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);
    }

    /**
     * Guard conditions to control who can perform transitions
     */
    public function onGuard(GuardEvent $event): void
    {
        /** @var Inquiry $inquiry */
        $inquiry = $event->getSubject();
        $transition = $event->getTransition()->getName();

        // Example guards - customize based on your needs
        switch ($transition) {
            case 'submit':
                // Only allow submission if inquiry can be submitted
                if (!$inquiry->canBeSubmitted()) {
                    $event->setBlocked(true, 'Inquiry cannot be submitted - missing required fields');

                    $this->logger->warning('Inquiry submission blocked', [
                        'inquiry_id' => $inquiry->getId()->toRfc4122(),
                        'errors' => $inquiry->getSubmissionErrors()
                    ]);
                }
                break;

            case 'complete':
                // Only allow completion from in_progress status
                // (already handled by workflow definition, but can add extra logic here)
                break;

            // Add more guards as needed
        }
    }
}
