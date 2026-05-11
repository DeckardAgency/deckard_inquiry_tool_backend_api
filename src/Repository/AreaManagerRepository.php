<?php

namespace App\Repository;

use App\Entity\Area;
use App\Entity\AreaManager;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AreaManager>
 */
class AreaManagerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AreaManager::class);
    }

    /**
     * Find all active managers for an area
     *
     * @return AreaManager[]
     */
    public function findActiveByArea(Area $area): array
    {
        return $this->createQueryBuilder('am')
            ->innerJoin('am.manager', 'm')
            ->where('am.area = :area')
            ->andWhere('am.isActive = :active')
            ->andWhere('m.isActive = :active')
            ->setParameter('area', $area)
            ->setParameter('active', true)
            ->orderBy('am.isPrimary', 'DESC')
            ->addOrderBy('am.currentAssignmentCount', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find primary manager for an area
     */
    public function findPrimaryByArea(Area $area): ?AreaManager
    {
        return $this->createQueryBuilder('am')
            ->innerJoin('am.manager', 'm')
            ->where('am.area = :area')
            ->andWhere('am.isPrimary = :primary')
            ->andWhere('am.isActive = :active')
            ->andWhere('m.isActive = :active')
            ->setParameter('area', $area)
            ->setParameter('primary', true)
            ->setParameter('active', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find managers with available capacity
     *
     * @return AreaManager[]
     */
    public function findAvailableByArea(Area $area): array
    {
        return $this->createQueryBuilder('am')
            ->innerJoin('am.manager', 'm')
            ->where('am.area = :area')
            ->andWhere('am.isActive = :active')
            ->andWhere('m.isActive = :active')
            ->andWhere('(am.maxCapacity = 0 OR am.currentAssignmentCount < am.maxCapacity)')
            ->setParameter('area', $area)
            ->setParameter('active', true)
            ->orderBy('am.isPrimary', 'DESC')
            ->addOrderBy('am.currentAssignmentCount', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all areas managed by a user
     *
     * @return AreaManager[]
     */
    public function findByManager(User $manager): array
    {
        return $this->createQueryBuilder('am')
            ->where('am.manager = :manager')
            ->andWhere('am.isActive = :active')
            ->setParameter('manager', $manager)
            ->setParameter('active', true)
            ->orderBy('am.isPrimary', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find manager with least assignments in area
     */
    public function findLeastLoadedByArea(Area $area): ?AreaManager
    {
        return $this->createQueryBuilder('am')
            ->innerJoin('am.manager', 'm')
            ->where('am.area = :area')
            ->andWhere('am.isActive = :active')
            ->andWhere('m.isActive = :active')
            ->andWhere('(am.maxCapacity = 0 OR am.currentAssignmentCount < am.maxCapacity)')
            ->setParameter('area', $area)
            ->setParameter('active', true)
            ->orderBy('am.currentAssignmentCount', 'ASC')
            ->addOrderBy('am.isPrimary', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find managers by specialization
     *
     * @return AreaManager[]
     */
    public function findBySpecialization(Area $area, string $specialization): array
    {
        return $this->createQueryBuilder('am')
            ->innerJoin('am.manager', 'm')
            ->where('am.area = :area')
            ->andWhere('am.isActive = :active')
            ->andWhere('m.isActive = :active')
            ->andWhere('JSON_CONTAINS(am.specializations, :specialization) = 1')
            ->setParameter('area', $area)
            ->setParameter('active', true)
            ->setParameter('specialization', json_encode($specialization))
            ->orderBy('am.currentAssignmentCount', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
