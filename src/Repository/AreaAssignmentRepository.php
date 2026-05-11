<?php

namespace App\Repository;

use App\Entity\AreaAssignment;
use App\Entity\AreaManager;
use App\Entity\Inquiry;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AreaAssignment>
 */
class AreaAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AreaAssignment::class);
    }

    /**
     * Find active assignment for inquiry
     */
    public function findActiveByInquiry(Inquiry $inquiry): ?AreaAssignment
    {
        return $this->createQueryBuilder('aa')
            ->where('aa.inquiry = :inquiry')
            ->andWhere('aa.isActive = :active')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('active', true)
            ->orderBy('aa.assignedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active assignment for order
     */
    public function findActiveByOrder(Order $order): ?AreaAssignment
    {
        return $this->createQueryBuilder('aa')
            ->where('aa.order = :order')
            ->andWhere('aa.isActive = :active')
            ->setParameter('order', $order)
            ->setParameter('active', true)
            ->orderBy('aa.assignedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active assignments for a manager
     *
     * @return AreaAssignment[]
     */
    public function findActiveByManager(AreaManager $areaManager): array
    {
        return $this->createQueryBuilder('aa')
            ->where('aa.areaManager = :areaManager')
            ->andWhere('aa.isActive = :active')
            ->setParameter('areaManager', $areaManager)
            ->setParameter('active', true)
            ->orderBy('aa.assignedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find assignment history for inquiry
     *
     * @return AreaAssignment[]
     */
    public function findHistoryByInquiry(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('aa')
            ->where('aa.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry)
            ->orderBy('aa.assignedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find assignment history for order
     *
     * @return AreaAssignment[]
     */
    public function findHistoryByOrder(Order $order): array
    {
        return $this->createQueryBuilder('aa')
            ->where('aa.order = :order')
            ->setParameter('order', $order)
            ->orderBy('aa.assignedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active assignments for a manager
     */
    public function countActiveByManager(AreaManager $areaManager): int
    {
        return $this->createQueryBuilder('aa')
            ->select('COUNT(aa.id)')
            ->where('aa.areaManager = :areaManager')
            ->andWhere('aa.isActive = :active')
            ->setParameter('areaManager', $areaManager)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find assignments by date range
     *
     * @return AreaAssignment[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('aa')
            ->where('aa.assignedAt >= :startDate')
            ->andWhere('aa.assignedAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('aa.assignedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
