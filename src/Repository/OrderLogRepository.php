<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderLog>
 *
 * @method OrderLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderLog[]    findAll()
 * @method OrderLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderLog::class);
    }

    /**
     * Find all logs for a specific order
     *
     * @return OrderLog[]
     */
    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('ol')
            ->andWhere('ol.order = :order')
            ->setParameter('order', $order)
            ->orderBy('ol.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by status transition
     *
     * @return OrderLog[]
     */
    public function findByStatusTransition(string $fromStatus, string $toStatus): array
    {
        return $this->createQueryBuilder('ol')
            ->andWhere('ol.previousStatus = :fromStatus')
            ->andWhere('ol.newStatus = :toStatus')
            ->setParameter('fromStatus', $fromStatus)
            ->setParameter('toStatus', $toStatus)
            ->orderBy('ol.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs changed by a specific user
     *
     * @return OrderLog[]
     */
    public function findByChangedBy(User $user): array
    {
        return $this->createQueryBuilder('ol')
            ->andWhere('ol.changedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('ol.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs within a date range
     *
     * @return OrderLog[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ol')
            ->andWhere('ol.createdAt >= :startDate')
            ->andWhere('ol.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ol.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the latest log entry for an order
     */
    public function findLatestForOrder(Order $order): ?OrderLog
    {
        return $this->createQueryBuilder('ol')
            ->andWhere('ol.order = :order')
            ->setParameter('order', $order)
            ->orderBy('ol.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count logs by status for analytics
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('ol')
            ->select('ol.newStatus as status, COUNT(ol.id) as count')
            ->groupBy('ol.newStatus')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }
}
