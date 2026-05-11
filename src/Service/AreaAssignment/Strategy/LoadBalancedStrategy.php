<?php

namespace App\Service\AreaAssignment\Strategy;

use App\Entity\Area;
use App\Entity\AreaManager;
use App\Repository\AreaManagerRepository;
use App\Service\AreaAssignment\AreaAssignmentStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * Assigns area manager based on capacity and current load
 */
class LoadBalancedStrategy implements AreaAssignmentStrategyInterface
{
    public function __construct(
        private readonly AreaManagerRepository $areaManagerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'load_balanced';
    }

    public function getPriority(): int
    {
        return 60; // Higher priority than round-robin
    }

    public function supports(Area $area, array $context = []): bool
    {
        // Check if area has managers with capacity limits
        $managers = $this->areaManagerRepository->findAvailableByArea($area);
        return !empty($managers);
    }

    public function assign(Area $area, array $context = []): ?AreaManager
    {
        // Get manager with least load and available capacity
        $leastLoadedManager = $this->areaManagerRepository->findLeastLoadedByArea($area);

        if ($leastLoadedManager === null) {
            $this->logger->warning('No available manager with capacity for load-balanced assignment', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
            ]);
            return null;
        }

        $this->logger->info('Manager assigned via load-balanced strategy', [
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'manager_id' => $leastLoadedManager->getId()->toRfc4122(),
            'manager_name' => $leastLoadedManager->getManager()->getFullName(),
            'current_assignments' => $leastLoadedManager->getCurrentAssignmentCount(),
            'max_capacity' => $leastLoadedManager->getMaxCapacity(),
            'available_capacity' => $leastLoadedManager->getAvailableCapacity(),
        ]);

        return $leastLoadedManager;
    }
}
