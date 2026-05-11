<?php

namespace App\Service\AreaAssignment\Strategy;

use App\Entity\Area;
use App\Entity\AreaManager;
use App\Repository\AreaCriteriaRepository;
use App\Repository\AreaManagerRepository;
use App\Service\AreaAssignment\AreaAssignmentStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * Assigns area manager based on matching criteria rules
 */
class CriteriaMatchStrategy implements AreaAssignmentStrategyInterface
{
    public function __construct(
        private readonly AreaCriteriaRepository $areaCriteriaRepository,
        private readonly AreaManagerRepository $areaManagerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'criteria_match';
    }

    public function getPriority(): int
    {
        return 100; // Highest priority - criteria-based assignment is most specific
    }

    public function supports(Area $area, array $context = []): bool
    {
        // Check if area has active criteria
        $criteria = $this->areaCriteriaRepository->findActiveByArea($area);
        return !empty($criteria);
    }

    public function assign(Area $area, array $context = []): ?AreaManager
    {
        $criteria = $this->areaCriteriaRepository->findActiveByArea($area);

        if (empty($criteria)) {
            $this->logger->info('No active criteria found for area', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
            ]);
            return null;
        }

        // Check if all criteria match
        $allMatch = true;
        foreach ($criteria as $criterion) {
            if (!$criterion->evaluate($context)) {
                $allMatch = false;
                $this->logger->debug('Criterion did not match', [
                    'criterion_id' => $criterion->getId()->toRfc4122(),
                    'criterion_name' => $criterion->getName(),
                    'field_type' => $criterion->getFieldType(),
                ]);
                break;
            }
        }

        if (!$allMatch) {
            $this->logger->info('Not all criteria matched for area', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
            ]);
            return null;
        }

        // All criteria matched - get available manager
        $availableManagers = $this->areaManagerRepository->findAvailableByArea($area);

        if (empty($availableManagers)) {
            $this->logger->warning('No available managers found for area despite criteria match', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
            ]);
            return null;
        }

        // Return first available manager (already ordered by priority and load)
        $assignedManager = $availableManagers[0];

        $this->logger->info('Manager assigned via criteria match', [
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'manager_id' => $assignedManager->getId()->toRfc4122(),
            'manager_name' => $assignedManager->getManager()->getFullName(),
        ]);

        return $assignedManager;
    }
}
