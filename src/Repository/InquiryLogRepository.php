<?php

namespace App\Repository;

use App\Entity\Inquiry;
use App\Entity\InquiryLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InquiryLog>
 */
class InquiryLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InquiryLog::class);
    }

    /**
     * Find all logs for a specific inquiry, ordered by creation date
     *
     * @return InquiryLog[]
     */
    public function findByInquiry(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('il')
            ->andWhere('il.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry)
            ->orderBy('il.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get inquiry history with pagination
     *
     * @return InquiryLog[]
     */
    public function getInquiryHistory(Inquiry $inquiry, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('il')
            ->andWhere('il.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry)
            ->orderBy('il.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count status changes for an inquiry
     */
    public function countStatusChanges(Inquiry $inquiry): int
    {
        return $this->createQueryBuilder('il')
            ->select('COUNT(il.id)')
            ->andWhere('il.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
