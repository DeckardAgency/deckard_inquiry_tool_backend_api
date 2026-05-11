<?php

namespace App\Repository;

use App\Entity\Area;
use App\Entity\AreaCriteria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AreaCriteria>
 */
class AreaCriteriaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AreaCriteria::class);
    }

    /**
     * Find all active criteria for an area
     *
     * @return AreaCriteria[]
     */
    public function findActiveByArea(Area $area): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.area = :area')
            ->andWhere('ac.isActive = :active')
            ->setParameter('area', $area)
            ->setParameter('active', true)
            ->orderBy('ac.priority', 'DESC')
            ->addOrderBy('ac.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find criteria by field type
     *
     * @return AreaCriteria[]
     */
    public function findByFieldType(string $fieldType): array
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.fieldType = :fieldType')
            ->andWhere('ac.isActive = :active')
            ->setParameter('fieldType', $fieldType)
            ->setParameter('active', true)
            ->orderBy('ac.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
