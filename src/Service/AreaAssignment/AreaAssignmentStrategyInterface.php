<?php

namespace App\Service\AreaAssignment;

use App\Entity\Area;
use App\Entity\AreaManager;
use App\Entity\Company;

/**
 * Interface for area manager assignment strategies
 */
interface AreaAssignmentStrategyInterface
{
    /**
     * Get strategy name
     */
    public function getName(): string;

    /**
     * Assign an area manager based on this strategy
     *
     * @param Area $area The area to assign from
     * @param array $context Context data about the inquiry/order for decision making
     * @return AreaManager|null The assigned area manager or null if none available
     */
    public function assign(Area $area, array $context = []): ?AreaManager;

    /**
     * Check if this strategy supports the given area
     */
    public function supports(Area $area, array $context = []): bool;

    /**
     * Get priority of this strategy (higher = more important)
     * Used when multiple strategies are available
     */
    public function getPriority(): int;
}
