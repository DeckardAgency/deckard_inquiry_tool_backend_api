<?php

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<OrderItem>
 *
 * @method OrderItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderItem[]    findAll()
 * @method OrderItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * Find all items for a specific order
     *
     * @param Uuid|Order $order
     * @return OrderItem[]
     */
    public function findByOrder(Uuid|Order $order): array
    {
        if ($order instanceof Order) {
            $order = $order->getId();
        }

        return $this->findBy(['orderRef' => $order]);
    }

    /**
     * Find all order items for a specific product
     *
     * @param Uuid|Product $product
     * @return OrderItem[]
     */
    public function findByProduct(Uuid|Product $product): array
    {
        if ($product instanceof Product) {
            $product = $product->getId();
        }

        return $this->findBy(['product' => $product]);
    }

    /**
     * Count how many times a product has been ordered
     */
    public function countProductOrders(Uuid|Product $product): int
    {
        if ($product instanceof Product) {
            $product = $product->getId();
        }

        return $this->count(['product' => $product]);
    }

    /**
     * Calculate total quantity sold for a product
     */
    public function getTotalQuantitySold(Uuid|Product $product): int
    {
        if ($product instanceof Product) {
            $product = $product->getId();
        }

        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.quantity) as totalQuantity')
            ->join('i.orderRef', 'o')
            ->andWhere('i.product = :product')
            ->andWhere('o.status != :canceledStatus')
            ->setParameter('product', $product, 'uuid')
            ->setParameter('canceledStatus', Order::STATUS_CANCELED)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }

    /**
     * Find best-selling products based on quantity sold
     *
     * @param int $limit
     * @return array Array of [productId, totalQuantity]
     */
    public function findBestSellingProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->select('IDENTITY(i.product) as productId', 'SUM(i.quantity) as totalQuantity')
            ->join('i.orderRef', 'o')
            ->andWhere('o.status != :canceledStatus')
            ->setParameter('canceledStatus', Order::STATUS_CANCELED)
            ->groupBy('i.product')
            ->orderBy('totalQuantity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all order items within a date range
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.orderRef', 'o')
            ->andWhere('o.createdAt >= :startDate')
            ->andWhere('o.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Save an order item to the database
     */
    public function save(OrderItem $orderItem, bool $flush = true): void
    {
        $this->getEntityManager()->persist($orderItem);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove an order item from the database
     */
    public function remove(OrderItem $orderItem, bool $flush = true): void
    {
        $this->getEntityManager()->remove($orderItem);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
