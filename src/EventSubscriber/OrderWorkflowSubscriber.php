<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\User;
use App\Message\OrderStatusChangedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;

class OrderWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly Security $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.order.completed' => 'onCompleted',
            'workflow.order.guard' => 'onGuard',
        ];
    }

    /**
     * Called when a workflow transition is completed
     */
    public function onCompleted(CompletedEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();

        // Skip draft orders
        if ($order->isDraft()) {
            return;
        }

        $transition = $event->getTransition();
        $marking = $event->getMarking();
        $places = $marking->getPlaces();
        $newStatus = array_key_first($places);

        // Skip notifications for pending_approval transitions (internal to client)
        if ($newStatus === Order::STATUS_PENDING_APPROVAL) {
            $this->logger->info('Skipping notification for pending approval order', [
                'order_id' => $order->getId()->toRfc4122(),
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

        $this->logger->info('Order workflow transition completed', [
            'order_id' => $order->getId()->toRfc4122(),
            'order_number' => $order->getOrderNumber(),
            'transition' => $transition->getName(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'workflow' => $event->getWorkflowName(),
            'modified_by' => $modifiedBy
        ]);

        // Dispatch status changed message for email notifications
        $message = new OrderStatusChangedMessage(
            $order->getId(),
            $oldStatus,  // old status from transition
            $newStatus   // new status
        );

        // Set modifiedBy information if available
        if ($modifiedBy) {
            $message->setModifiedBy($modifiedBy);
        }

        $this->messageBus->dispatch($message);

        $this->logger->info('OrderStatusChangedMessage dispatched', [
            'order_id' => $order->getId()->toRfc4122(),
            'new_status' => $newStatus
        ]);
    }

    /**
     * Guard conditions to control who can perform transitions
     */
    public function onGuard(GuardEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();
        $transition = $event->getTransition()->getName();

        // Example guards - customize based on your needs
        switch ($transition) {
            case 'dispatch':
                // Could add logic like: ensure shipping address exists
                if (empty($order->getShippingAddress())) {
                    $event->setBlocked(true, 'Cannot dispatch order without shipping address');

                    $this->logger->warning('Order dispatch blocked - missing shipping address', [
                        'order_id' => $order->getId()->toRfc4122()
                    ]);
                }
                break;

            // Add more guards as needed
        }
    }
}
