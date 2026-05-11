<?php

namespace App\Service;

use App\Entity\Area;
use App\Entity\AreaAssignment;
use App\Entity\AreaManager;
use App\Entity\Client;
use App\Entity\Inquiry;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\AreaAssignmentRepository;
use App\Repository\AreaRepository;
use App\Service\AreaAssignment\AreaAssignmentStrategyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Main service for area and manager assignment
 */
class AreaAssignmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AreaRepository $areaRepository,
        private readonly AreaAssignmentRepository $areaAssignmentRepository,
        private readonly iterable $strategies,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Auto-assign area manager to an inquiry
     */
    public function assignToInquiry(
        Inquiry $inquiry,
        ?string $strategyName = 'hybrid',
        ?User $assignedBy = null
    ): ?AreaAssignment {
        // Check if already assigned
        $existingAssignment = $this->areaAssignmentRepository->findActiveByInquiry($inquiry);
        if ($existingAssignment !== null) {
            $this->logger->info('Inquiry already has active assignment', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'inquiry_number' => $inquiry->getInquiryNumber(),
                'existing_assignment_id' => $existingAssignment->getId()->toRfc4122(),
            ]);
            return $existingAssignment;
        }

        // Get client from inquiry
        $client = $inquiry->getUser()?->getClient();
        if ($client === null) {
            $this->logger->warning('Cannot assign - inquiry has no associated client', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'inquiry_number' => $inquiry->getInquiryNumber(),
            ]);
            return null;
        }

        // Detect area for this inquiry
        $area = $this->detectArea($client, $this->buildInquiryContext($inquiry));
        if ($area === null) {
            $this->logger->warning('No matching area found for inquiry', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'inquiry_number' => $inquiry->getInquiryNumber(),
                'client_id' => $client->getId()->toRfc4122(),
            ]);
            return null;
        }

        // Assign manager using strategy
        $areaManager = $this->assignManager($area, $this->buildInquiryContext($inquiry), $strategyName);
        if ($areaManager === null) {
            $this->logger->warning('No manager could be assigned for inquiry', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'inquiry_number' => $inquiry->getInquiryNumber(),
                'area_id' => $area->getId()->toRfc4122(),
                'strategy' => $strategyName,
            ]);
            return null;
        }

        // Create assignment
        $assignment = $this->createAssignment(
            $areaManager,
            $inquiry,
            null,
            AreaAssignment::ASSIGNMENT_TYPE_AUTO,
            $strategyName,
            $assignedBy
        );

        $this->logger->info('Successfully assigned manager to inquiry', [
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'inquiry_number' => $inquiry->getInquiryNumber(),
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'manager_id' => $areaManager->getId()->toRfc4122(),
            'manager_name' => $areaManager->getManager()->getFullName(),
            'assignment_id' => $assignment->getId()->toRfc4122(),
            'strategy' => $strategyName,
        ]);

        return $assignment;
    }

    /**
     * Auto-assign area manager to an order
     */
    public function assignToOrder(
        Order $order,
        ?string $strategyName = 'hybrid',
        ?User $assignedBy = null
    ): ?AreaAssignment {
        // Check if already assigned
        $existingAssignment = $this->areaAssignmentRepository->findActiveByOrder($order);
        if ($existingAssignment !== null) {
            $this->logger->info('Order already has active assignment', [
                'order_id' => $order->getId()->toRfc4122(),
                'order_number' => $order->getOrderNumber(),
                'existing_assignment_id' => $existingAssignment->getId()->toRfc4122(),
            ]);
            return $existingAssignment;
        }

        // Get client from order
        $client = $order->getUser()?->getClient();
        if ($client === null) {
            $this->logger->warning('Cannot assign - order has no associated client', [
                'order_id' => $order->getId()->toRfc4122(),
                'order_number' => $order->getOrderNumber(),
            ]);
            return null;
        }

        // Detect area for this order
        $area = $this->detectArea($client, $this->buildOrderContext($order));
        if ($area === null) {
            $this->logger->warning('No matching area found for order', [
                'order_id' => $order->getId()->toRfc4122(),
                'order_number' => $order->getOrderNumber(),
                'client_id' => $client->getId()->toRfc4122(),
            ]);
            return null;
        }

        // Assign manager using strategy
        $areaManager = $this->assignManager($area, $this->buildOrderContext($order), $strategyName);
        if ($areaManager === null) {
            $this->logger->warning('No manager could be assigned for order', [
                'order_id' => $order->getId()->toRfc4122(),
                'order_number' => $order->getOrderNumber(),
                'area_id' => $area->getId()->toRfc4122(),
                'strategy' => $strategyName,
            ]);
            return null;
        }

        // Create assignment
        $assignment = $this->createAssignment(
            $areaManager,
            null,
            $order,
            AreaAssignment::ASSIGNMENT_TYPE_AUTO,
            $strategyName,
            $assignedBy
        );

        $this->logger->info('Successfully assigned manager to order', [
            'order_id' => $order->getId()->toRfc4122(),
            'order_number' => $order->getOrderNumber(),
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'manager_id' => $areaManager->getId()->toRfc4122(),
            'manager_name' => $areaManager->getManager()->getFullName(),
            'assignment_id' => $assignment->getId()->toRfc4122(),
            'strategy' => $strategyName,
        ]);

        return $assignment;
    }

    /**
     * Manually assign area manager
     */
    public function manualAssignment(
        AreaManager $areaManager,
        ?Inquiry $inquiry = null,
        ?Order $order = null,
        ?User $assignedBy = null,
        ?string $reason = null
    ): AreaAssignment {
        // Unassign existing if any
        if ($inquiry !== null) {
            $this->unassignFromInquiry($inquiry, $assignedBy, 'Replaced by manual assignment');
        }
        if ($order !== null) {
            $this->unassignFromOrder($order, $assignedBy, 'Replaced by manual assignment');
        }

        // Create new assignment
        $assignment = $this->createAssignment(
            $areaManager,
            $inquiry,
            $order,
            AreaAssignment::ASSIGNMENT_TYPE_MANUAL,
            null,
            $assignedBy,
            $reason
        );

        $entityType = $inquiry !== null ? 'inquiry' : 'order';
        $entityNumber = $inquiry?->getInquiryNumber() ?? $order?->getOrderNumber();

        $this->logger->info('Manual assignment created', [
            'entity_type' => $entityType,
            'entity_number' => $entityNumber,
            'manager_id' => $areaManager->getId()->toRfc4122(),
            'manager_name' => $areaManager->getManager()->getFullName(),
            'assigned_by' => $assignedBy?->getFullName(),
            'assignment_id' => $assignment->getId()->toRfc4122(),
        ]);

        return $assignment;
    }

    /**
     * Reassign to different manager
     */
    public function reassign(
        AreaAssignment $currentAssignment,
        AreaManager $newAreaManager,
        ?User $assignedBy = null,
        ?string $reason = null
    ): AreaAssignment {
        // Unassign current
        $currentAssignment->unassign($assignedBy, $reason);
        $this->entityManager->flush();

        // Create new assignment
        $assignment = $this->createAssignment(
            $newAreaManager,
            $currentAssignment->getInquiry(),
            $currentAssignment->getOrder(),
            AreaAssignment::ASSIGNMENT_TYPE_REASSIGNED,
            null,
            $assignedBy,
            $reason
        );

        $this->logger->info('Assignment reassigned', [
            'old_assignment_id' => $currentAssignment->getId()->toRfc4122(),
            'new_assignment_id' => $assignment->getId()->toRfc4122(),
            'old_manager' => $currentAssignment->getAreaManager()->getManager()->getFullName(),
            'new_manager' => $newAreaManager->getManager()->getFullName(),
            'reassigned_by' => $assignedBy?->getFullName(),
        ]);

        return $assignment;
    }

    /**
     * Unassign manager from inquiry
     */
    public function unassignFromInquiry(Inquiry $inquiry, ?User $unassignedBy = null, ?string $reason = null): void
    {
        $assignment = $this->areaAssignmentRepository->findActiveByInquiry($inquiry);
        if ($assignment !== null) {
            $assignment->unassign($unassignedBy, $reason);
            $assignment->getAreaManager()->decrementAssignmentCount();
            $this->entityManager->flush();

            $this->logger->info('Unassigned manager from inquiry', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'assignment_id' => $assignment->getId()->toRfc4122(),
                'unassigned_by' => $unassignedBy?->getFullName(),
            ]);
        }
    }

    /**
     * Unassign manager from order
     */
    public function unassignFromOrder(Order $order, ?User $unassignedBy = null, ?string $reason = null): void
    {
        $assignment = $this->areaAssignmentRepository->findActiveByOrder($order);
        if ($assignment !== null) {
            $assignment->unassign($unassignedBy, $reason);
            $assignment->getAreaManager()->decrementAssignmentCount();
            $this->entityManager->flush();

            $this->logger->info('Unassigned manager from order', [
                'order_id' => $order->getId()->toRfc4122(),
                'assignment_id' => $assignment->getId()->toRfc4122(),
                'unassigned_by' => $unassignedBy?->getFullName(),
            ]);
        }
    }

    /**
     * Get available managers for a client
     *
     * @return AreaManager[]
     */
    public function getAvailableManagers(Client $client): array
    {
        $managers = [];

        // Get areas directly from the client entity to avoid UUID comparison issues
        foreach ($client->getAreas() as $area) {
            if (!$area->isActive()) {
                continue;
            }
            foreach ($area->getActiveManagers() as $areaManager) {
                $managers[$areaManager->getId()->toRfc4122()] = $areaManager;
            }
        }

        return array_values($managers);
    }

    /**
     * Detect appropriate area for inquiry/order based on criteria
     */
    private function detectArea(Client $client, array $context): ?Area
    {
        $areas = $this->areaRepository->findActiveByClient($client);

        foreach ($areas as $area) {
            $criteria = $area->getAreaCriteria();
            if ($criteria->isEmpty()) {
                continue;
            }

            $allMatch = true;
            foreach ($criteria as $criterion) {
                if (!$criterion->isActive() || !$criterion->evaluate($context)) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return $area;
            }
        }

        // If no criteria match, return first active area
        return $areas[0] ?? null;
    }

    /**
     * Assign manager using specified strategy
     */
    private function assignManager(Area $area, array $context, ?string $strategyName = 'hybrid'): ?AreaManager
    {
        $strategy = $this->getStrategy($strategyName);

        if ($strategy === null) {
            $this->logger->error('Strategy not found', ['strategy' => $strategyName]);
            return null;
        }

        return $strategy->assign($area, $context);
    }

    /**
     * Get strategy by name
     */
    private function getStrategy(string $name): ?AreaAssignmentStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $name) {
                return $strategy;
            }
        }

        return null;
    }

    /**
     * Create assignment record
     */
    private function createAssignment(
        AreaManager $areaManager,
        ?Inquiry $inquiry,
        ?Order $order,
        string $assignmentType,
        ?string $strategyName,
        ?User $assignedBy,
        ?string $reason = null
    ): AreaAssignment {
        $assignment = new AreaAssignment();
        $assignment->setAreaManager($areaManager);
        $assignment->setInquiry($inquiry);
        $assignment->setOrder($order);
        $assignment->setAssignmentType($assignmentType);
        $assignment->setAssignmentStrategy($strategyName);
        $assignment->setAssignedBy($assignedBy);
        $assignment->setAssignmentReason($reason);

        // Increment manager assignment count
        $areaManager->incrementAssignmentCount();

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        return $assignment;
    }

    /**
     * Build context array from inquiry
     */
    private function buildInquiryContext(Inquiry $inquiry): array
    {
        return [
            'entity_type' => 'inquiry',
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'inquiry_number' => $inquiry->getInquiryNumber(),
            'user' => $inquiry->getUser(),
            'client' => $inquiry->getUser()?->getClient(),
            'country' => $inquiry->getUser()?->getClient()?->getCountry(),
            'created_at' => $inquiry->getCreatedAt(),
        ];
    }

    /**
     * Build context array from order
     */
    private function buildOrderContext(Order $order): array
    {
        return [
            'entity_type' => 'order',
            'order_id' => $order->getId()->toRfc4122(),
            'order_number' => $order->getOrderNumber(),
            'user' => $order->getUser(),
            'client' => $order->getUser()?->getClient(),
            'country' => $order->getUser()?->getClient()?->getCountry(),
            'total_price' => $order->getTotalPrice(),
            'created_at' => $order->getCreatedAt(),
        ];
    }
}
