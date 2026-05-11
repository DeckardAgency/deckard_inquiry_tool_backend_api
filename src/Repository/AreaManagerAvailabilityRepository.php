<?php

namespace App\Repository;

use App\Entity\AreaManager;
use App\Entity\AreaManagerAvailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AreaManagerAvailability>
 */
class AreaManagerAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AreaManagerAvailability::class);
    }

    /**
     * Find all active availabilities for an area manager
     *
     * @return AreaManagerAvailability[]
     */
    public function findActiveByAreaManager(AreaManager $areaManager): array
    {
        return $this->createQueryBuilder('ama')
            ->where('ama.areaManager = :areaManager')
            ->andWhere('ama.isActive = :active')
            ->setParameter('areaManager', $areaManager)
            ->setParameter('active', true)
            ->orderBy('ama.dayOfWeek', 'ASC')
            ->addOrderBy('ama.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find availability for specific day
     *
     * @return AreaManagerAvailability[]
     */
    public function findByDayOfWeek(AreaManager $areaManager, int $dayOfWeek): array
    {
        return $this->createQueryBuilder('ama')
            ->where('ama.areaManager = :areaManager')
            ->andWhere('ama.dayOfWeek = :dayOfWeek')
            ->andWhere('ama.isActive = :active')
            ->setParameter('areaManager', $areaManager)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('active', true)
            ->orderBy('ama.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
