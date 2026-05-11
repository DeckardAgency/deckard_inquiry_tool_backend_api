<?php

namespace App\Service\AreaAssignment\Strategy;

use App\Entity\Area;
use App\Entity\AreaManager;
use App\Repository\AreaAssignmentRepository;
use App\Repository\AreaManagerRepository;
use App\Service\AreaAssignment\AreaAssignmentStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * Assigns area managers in round-robin fashion
 */
class RoundRobinStrategy implements AreaAssignmentStrategyInterface
{
    public function __construct(
        private readonly AreaManagerRepository $areaManagerRepository,
        private readonly AreaAssignmentRepository $areaAssignmentRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'round_robin';
    }

    public function getPriority(): int
    {
        return 50; // Medium priority
    }

    public function supports(Area $area, array $context = []): bool
    {
        // Always supports if area has managers
        $managers = $this->areaManagerRepository->findAvailableByArea($area);
        return !empty($managers);
    }

    public function assign(Area $area, array $context = []): ?AreaManager
    {
        $availableManagers = $this->areaManagerRepository->findAvailableByArea($area);

        if (empty($availableManagers)) {
            $this->logger->warning('No available managers for round-robin assignment', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
            ]);
            return null;
        }

        // Get assignment counts for each manager
        $managerCounts = [];
        foreach ($availableManagers as $manager) {
            $count = $this->areaAssignmentRepository->countActiveByManager($manager);
            $managerCounts[$manager->getId()->toRfc4122()] = [
                'manager' => $manager,
                'count' => $count,
            ];
        }

        // Sort by count ascending to find manager with least assignments
        uasort($managerCounts, function ($a, $b) {
            return $a['count'] <=> $b['count'];
        });

        // Get manager with least assignments
        $selected = reset($managerCounts);
        $assignedManager = $selected['manager'];

        $this->logger->info('Manager assigned via round-robin', [
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'manager_id' => $assignedManager->getId()->toRfc4122(),
            'manager_name' => $assignedManager->getManager()->getFullName(),
            'current_assignments' => $selected['count'],
        ]);

        return $assignedManager;
    }
}
