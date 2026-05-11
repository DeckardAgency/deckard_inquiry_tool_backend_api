<?php

namespace App\Service\AreaAssignment\Strategy;

use App\Entity\Area;
use App\Entity\AreaManager;
use App\Repository\AreaManagerRepository;
use App\Service\AreaAssignment\AreaAssignmentStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * Assigns area manager based on current availability schedule
 */
class AvailabilityStrategy implements AreaAssignmentStrategyInterface
{
    public function __construct(
        private readonly AreaManagerRepository $areaManagerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'availability';
    }

    public function getPriority(): int
    {
        return 70; // Higher priority - availability is important
    }

    public function supports(Area $area, array $context = []): bool
    {
        $managers = $this->areaManagerRepository->findAvailableByArea($area);
        return !empty($managers);
    }

    public function assign(Area $area, array $context = []): ?AreaManager
    {
        $availableManagers = $this->areaManagerRepository->findAvailableByArea($area);

        if (empty($availableManagers)) {
            $this->logger->warning('No available managers for availability-based assignment', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
            ]);
            return null;
        }

        $now = new \DateTime();
        $currentlyAvailableManagers = [];

        // Filter managers by current availability
        foreach ($availableManagers as $manager) {
            if ($manager->isAvailableAt($now)) {
                $currentlyAvailableManagers[] = $manager;
            }
        }

        if (empty($currentlyAvailableManagers)) {
            $this->logger->info('No managers currently available based on schedule, using first available', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
                'current_time' => $now->format('Y-m-d H:i:s'),
            ]);
            // Fall back to first available manager if none are currently available
            $assignedManager = $availableManagers[0];
        } else {
            // Select manager with least load among currently available
            usort($currentlyAvailableManagers, function (AreaManager $a, AreaManager $b) {
                return $a->getCurrentAssignmentCount() <=> $b->getCurrentAssignmentCount();
            });

            $assignedManager = $currentlyAvailableManagers[0];
        }

        $this->logger->info('Manager assigned via availability strategy', [
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'manager_id' => $assignedManager->getId()->toRfc4122(),
            'manager_name' => $assignedManager->getManager()->getFullName(),
            'is_currently_available' => in_array($assignedManager, $currentlyAvailableManagers, true),
            'current_time' => $now->format('Y-m-d H:i:s'),
        ]);

        return $assignedManager;
    }
}
