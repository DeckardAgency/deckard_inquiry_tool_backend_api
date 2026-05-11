<?php

namespace App\Service\AreaAssignment\Strategy;

use App\Entity\Area;
use App\Entity\AreaManager;
use App\Service\AreaAssignment\AreaAssignmentStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * Combines multiple strategies for intelligent assignment
 * Order: Criteria Match -> Availability -> Load Balanced -> Round Robin
 */
class HybridStrategy implements AreaAssignmentStrategyInterface
{
    /**
     * @param iterable<AreaAssignmentStrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'hybrid';
    }

    public function getPriority(): int
    {
        return 90; // Second highest - just below criteria match
    }

    public function supports(Area $area, array $context = []): bool
    {
        // Always supports - will try multiple strategies
        return true;
    }

    public function assign(Area $area, array $context = []): ?AreaManager
    {
        $supportedStrategies = [];

        // Collect all strategies that support this area (excluding self)
        foreach ($this->strategies as $strategy) {
            if ($strategy instanceof self) {
                continue; // Skip self to avoid infinite recursion
            }

            if ($strategy->supports($area, $context)) {
                $supportedStrategies[] = $strategy;
            }
        }

        if (empty($supportedStrategies)) {
            $this->logger->warning('No strategies support this area for hybrid assignment', [
                'area_id' => $area->getId()->toRfc4122(),
                'area_code' => $area->getCode(),
            ]);
            return null;
        }

        // Sort strategies by priority (descending)
        usort($supportedStrategies, function (AreaAssignmentStrategyInterface $a, AreaAssignmentStrategyInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        $this->logger->debug('Hybrid strategy trying multiple strategies', [
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'strategies_count' => count($supportedStrategies),
            'strategies' => array_map(fn($s) => $s->getName(), $supportedStrategies),
        ]);

        // Try each strategy in priority order
        foreach ($supportedStrategies as $strategy) {
            $this->logger->debug('Hybrid strategy trying: ' . $strategy->getName(), [
                'area_id' => $area->getId()->toRfc4122(),
                'strategy' => $strategy->getName(),
                'priority' => $strategy->getPriority(),
            ]);

            $manager = $strategy->assign($area, $context);

            if ($manager !== null) {
                $this->logger->info('Manager assigned via hybrid strategy', [
                    'area_id' => $area->getId()->toRfc4122(),
                    'area_code' => $area->getCode(),
                    'manager_id' => $manager->getId()->toRfc4122(),
                    'manager_name' => $manager->getManager()->getFullName(),
                    'successful_strategy' => $strategy->getName(),
                ]);
                return $manager;
            }
        }

        $this->logger->warning('All strategies failed to assign manager in hybrid mode', [
            'area_id' => $area->getId()->toRfc4122(),
            'area_code' => $area->getCode(),
            'tried_strategies' => array_map(fn($s) => $s->getName(), $supportedStrategies),
        ]);

        return null;
    }
}
