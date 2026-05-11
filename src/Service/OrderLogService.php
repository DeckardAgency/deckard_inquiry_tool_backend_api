<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class OrderLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    /**
     * Log order status change (excluding transitions to draft)
     */
    public function logStatusChange(
        Order $order,
        string $previousStatus,
        string $newStatus,
        ?string $comment = null,
        ?array $metadata = null
    ): ?OrderLog {
        // Skip logging if new status is draft (but allow transitions FROM draft)
        if ($newStatus === Order::STATUS_DRAFT) {
            return null;
        }

        // Skip if status hasn't actually changed
        if ($previousStatus === $newStatus) {
            return null;
        }

        $log = new OrderLog();
        $log->setOrder($order);
        $log->setPreviousStatus($previousStatus);
        $log->setNewStatus($newStatus);
        $log->setComment($comment);

        // Set the user who made the change
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $log->setChangedBy($currentUser);
        }

        // Add metadata if provided
        if ($metadata !== null) {
            $log->setMetadata($metadata);
        }

        // Add order details to metadata
        $log->addMetadata('order_number', $order->getOrderNumber());
        $log->addMetadata('order_total', $order->getTotalAmount());
        $log->addMetadata('items_count', $order->getItems()->count());

        $this->entityManager->persist($log);
        $order->addLog($log);

        return $log;
    }

    /**
     * Log order submission (transition from draft to submitted)
     */
    public function logOrderSubmission(Order $order, ?string $comment = null): ?OrderLog
    {
        // Special case: log when transitioning FROM draft to submitted
        if ($order->getStatus() === Order::STATUS_SUBMITTED) {
            $log = new OrderLog();
            $log->setOrder($order);
            $log->setPreviousStatus(Order::STATUS_DRAFT);
            $log->setNewStatus(Order::STATUS_SUBMITTED);
            $log->setComment($comment ?? 'Order submitted from draft');

            $currentUser = $this->security->getUser();
            if ($currentUser instanceof User) {
                $log->setChangedBy($currentUser);
            }

            $log->addMetadata('order_number', $order->getOrderNumber());
            $log->addMetadata('order_total', $order->getTotalAmount());
            $log->addMetadata('items_count', $order->getItems()->count());
            $log->addMetadata('submission_type', 'draft_to_submitted');

            $this->entityManager->persist($log);
            $order->addLog($log);

            return $log;
        }

        return null;
    }

    /**
     * Get status change history for an order (excluding draft)
     *
     * @return OrderLog[]
     */
    public function getOrderHistory(Order $order): array
    {
        return $this->entityManager->getRepository(OrderLog::class)
            ->findByOrder($order);
    }

    /**
     * Get the last status change for an order
     */
    public function getLastStatusChange(Order $order): ?OrderLog
    {
        return $this->entityManager->getRepository(OrderLog::class)
            ->findLatestForOrder($order);
    }

    /**
     * Log bulk status change for multiple orders
     *
     * @param Order[] $orders
     */
    public function logBulkStatusChange(
        array $orders,
        string $newStatus,
        ?string $comment = null
    ): array {
        $logs = [];

        foreach ($orders as $order) {
            $previousStatus = $order->getStatus();
            $order->setStatus($newStatus);

            $log = $this->logStatusChange(
                $order,
                $previousStatus,
                $newStatus,
                $comment,
                ['bulk_update' => true]
            );

            if ($log !== null) {
                $logs[] = $log;
            }
        }

        return $logs;
    }
}
