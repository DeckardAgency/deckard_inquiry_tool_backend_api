<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Find an order by its order number
     */
    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return $this->findOneBy(['orderNumber' => $orderNumber]);
    }

    /**
     * Find orders by status
     *
     * @param string $status
     * @return Order[]
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['createdAt' => 'DESC']);
    }

    /**
     * Find all draft orders for a user
     *
     * @param Uuid|User $user
     * @return Order[]
     */
    public function findDraftsByUser(Uuid|User $user): array
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->findBy(
            ['user' => $user, 'isDraft' => true],
            ['lastSavedAt' => 'DESC']
        );
    }

    /**
     * Find a specific draft order by ID and user
     *
     * @param Uuid $orderId
     * @param Uuid|User $user
     * @return Order|null
     */
    public function findDraftByIdAndUser(Uuid $orderId, Uuid|User $user): ?Order
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->findOneBy([
            'id' => $orderId,
            'user' => $user,
            'isDraft' => true
        ]);
    }

    /**
     * Count draft orders for a specific user
     */
    public function countDraftsByUser(Uuid|User $user): int
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->count(['user' => $user, 'isDraft' => true]);
    }

    /**
     * Find abandoned draft orders (not updated for a period)
     *
     * @param \DateTimeInterface $cutoffDate Orders not updated since this date
     * @return Order[]
     */
    public function findAbandonedDrafts(\DateTimeInterface $cutoffDate): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.isDraft = :isDraft')
            ->andWhere('o.lastSavedAt < :cutoffDate')
            ->setParameter('isDraft', true)
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders by user
     *
     * @param Uuid|User $user
     * @return Order[]
     */
    public function findByUser(Uuid|User $user): array
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Find orders by user and status
     *
     * @param Uuid|User $user
     * @param string $status
     * @return Order[]
     */
    public function findByUserAndStatus(Uuid|User $user, string $status): array
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->findBy(
            ['user' => $user, 'status' => $status],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Find orders by status with pagination
     *
     * @param string $status
     * @param int $page
     * @param int $limit
     * @return Order[]
     */
    public function findByStatusPaginated(string $status, int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        return $this->findBy(
            ['status' => $status],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
    }

    /**
     * Find orders by user with pagination
     *
     * @param Uuid|User $user
     * @param int $page
     * @param int $limit
     * @return Order[]
     */
    public function findByUserPaginated(Uuid|User $user, int $page = 1, int $limit = 10): array
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        $offset = ($page - 1) * $limit;

        return $this->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
    }

    /**
     * Find recent orders
     *
     * @param int $limit
     * @return Order[]
     */
    public function findRecentOrders(int $limit = 10): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Find orders by date range
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return Order[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.createdAt >= :startDate')
            ->andWhere('o.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count orders by status
     */
    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    /**
     * Count orders by user
     */
    public function countByUser(Uuid|User $user): int
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->count(['user' => $user]);
    }

    /**
     * Find orders that contain a specific product
     *
     * @param Uuid $productId
     * @return Order[]
     */
    public function findOrdersContainingProduct(Uuid $productId): array
    {
        return $this->createQueryBuilder('o')
            ->join('o.items', 'i')
            ->join('i.product', 'p')
            ->andWhere('p.id = :productId')
            ->setParameter('productId', $productId, 'uuid')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total revenue in a date range
     */
    public function calculateTotalRevenue(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as totalRevenue')
            ->andWhere('o.status != :canceledStatus')
            ->andWhere('o.createdAt >= :startDate')
            ->andWhere('o.createdAt <= :endDate')
            ->setParameter('canceledStatus', Order::STATUS_CANCELED)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float)$result : 0.0;
    }

    /**
     * Calculate total revenue by user
     */
    public function calculateTotalRevenueByUser(Uuid|User $user): float
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as totalRevenue')
            ->andWhere('o.user = :userId')
            ->andWhere('o.status != :canceledStatus')
            ->setParameter('userId', $user, 'uuid')
            ->setParameter('canceledStatus', Order::STATUS_CANCELED)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float)$result : 0.0;
    }

    /**
     * Save an order to the database
     */
    public function save(Order $order, bool $flush = true): void
    {
        $this->_em->persist($order);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Remove an order from the database
     */
    public function remove(Order $order, bool $flush = true): void
    {
        $this->_em->remove($order);

        if ($flush) {
            $this->_em->flush();
        }
    }
}
