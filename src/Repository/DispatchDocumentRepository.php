<?php

namespace App\Repository;

use App\Entity\DispatchDocument;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DispatchDocument>
 */
class DispatchDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DispatchDocument::class);
    }

    /**
     * Find all documents for a specific order
     *
     * @return DispatchDocument[]
     */
    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.order = :order')
            ->setParameter('order', $order)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents by type for an order
     *
     * @return DispatchDocument[]
     */
    public function findByOrderAndType(Order $order, string $documentType): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.order = :order')
            ->andWhere('d.documentType = :type')
            ->setParameter('order', $order)
            ->setParameter('type', $documentType)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
