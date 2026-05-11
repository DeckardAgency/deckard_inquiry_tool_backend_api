<?php

namespace App\Repository;

use App\Entity\Inquiry;
use App\Entity\InquiryOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InquiryOffer>
 *
 * @method InquiryOffer|null find($id, $lockMode = null, $lockVersion = null)
 * @method InquiryOffer|null findOneBy(array $criteria, array $orderBy = null)
 * @method InquiryOffer[]    findAll()
 * @method InquiryOffer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InquiryOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InquiryOffer::class);
    }

    /**
     * Find all offers for an inquiry
     */
    public function findByInquiry(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find sent offers for an inquiry (visible to client)
     */
    public function findSentByInquiry(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.inquiry = :inquiry')
            ->andWhere('o.status != :draft')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('draft', InquiryOffer::STATUS_DRAFT)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest sent offer for an inquiry
     */
    public function findLatestSentByInquiry(Inquiry $inquiry): ?InquiryOffer
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.inquiry = :inquiry')
            ->andWhere('o.status = :status')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('status', InquiryOffer::STATUS_SENT)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count offers by status for an inquiry
     */
    public function countByInquiryAndStatus(Inquiry $inquiry, string $status): int
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.inquiry = :inquiry')
            ->andWhere('o.status = :status')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find offers with their items (eager loaded)
     */
    public function findByInquiryWithItems(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('i')
            ->leftJoin('o.items', 'i')
            ->andWhere('o.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
