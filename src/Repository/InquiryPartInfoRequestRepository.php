<?php

namespace App\Repository;

use App\Entity\Inquiry;
use App\Entity\InquiryMachinePart;
use App\Entity\InquiryPartInfoRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InquiryPartInfoRequest>
 *
 * @method InquiryPartInfoRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method InquiryPartInfoRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method InquiryPartInfoRequest[]    findAll()
 * @method InquiryPartInfoRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InquiryPartInfoRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InquiryPartInfoRequest::class);
    }

    /**
     * Find all pending info requests for an inquiry
     */
    public function findPendingByInquiry(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.inquiry = :inquiry')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('statuses', [
                InquiryPartInfoRequest::STATUS_PENDING,
                InquiryPartInfoRequest::STATUS_NEEDS_REVISION
            ])
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all responded info requests for an inquiry (awaiting admin review)
     */
    public function findRespondedByInquiry(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.inquiry = :inquiry')
            ->andWhere('r.status = :status')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('status', InquiryPartInfoRequest::STATUS_RESPONDED)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find info requests by part
     */
    public function findByPart(InquiryMachinePart $part): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.inquiryMachinePart = :part')
            ->setParameter('part', $part)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest pending info request for a part
     */
    public function findLatestPendingByPart(InquiryMachinePart $part): ?InquiryPartInfoRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.inquiryMachinePart = :part')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('part', $part)
            ->setParameter('statuses', [
                InquiryPartInfoRequest::STATUS_PENDING,
                InquiryPartInfoRequest::STATUS_NEEDS_REVISION
            ])
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count pending info requests for an inquiry
     */
    public function countPendingByInquiry(Inquiry $inquiry): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.inquiry = :inquiry')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('statuses', [
                InquiryPartInfoRequest::STATUS_PENDING,
                InquiryPartInfoRequest::STATUS_NEEDS_REVISION
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count responded info requests for an inquiry
     */
    public function countRespondedByInquiry(Inquiry $inquiry): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.inquiry = :inquiry')
            ->andWhere('r.status = :status')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('status', InquiryPartInfoRequest::STATUS_RESPONDED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all info requests with their messages (eager loaded)
     */
    public function findByInquiryWithMessages(Inquiry $inquiry): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('m')
            ->leftJoin('r.messages', 'm')
            ->andWhere('r.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry)
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
