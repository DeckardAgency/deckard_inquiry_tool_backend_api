<?php

namespace App\Repository;

use App\Entity\InquiryPartInfoMessage;
use App\Entity\InquiryPartInfoRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InquiryPartInfoMessage>
 *
 * @method InquiryPartInfoMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method InquiryPartInfoMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method InquiryPartInfoMessage[]    findAll()
 * @method InquiryPartInfoMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InquiryPartInfoMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InquiryPartInfoMessage::class);
    }

    /**
     * Find all messages for an info request, ordered by creation date
     */
    public function findByInfoRequest(InquiryPartInfoRequest $infoRequest): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.infoRequest = :infoRequest')
            ->setParameter('infoRequest', $infoRequest)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest message in an info request
     */
    public function findLatestByInfoRequest(InquiryPartInfoRequest $infoRequest): ?InquiryPartInfoMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.infoRequest = :infoRequest')
            ->setParameter('infoRequest', $infoRequest)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all client messages for an info request
     */
    public function findClientMessagesByInfoRequest(InquiryPartInfoRequest $infoRequest): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.infoRequest = :infoRequest')
            ->andWhere('m.senderType = :senderType')
            ->setParameter('infoRequest', $infoRequest)
            ->setParameter('senderType', InquiryPartInfoMessage::SENDER_TYPE_CLIENT)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all admin messages for an info request
     */
    public function findAdminMessagesByInfoRequest(InquiryPartInfoRequest $infoRequest): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.infoRequest = :infoRequest')
            ->andWhere('m.senderType = :senderType')
            ->setParameter('infoRequest', $infoRequest)
            ->setParameter('senderType', InquiryPartInfoMessage::SENDER_TYPE_ADMIN)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count messages in an info request
     */
    public function countByInfoRequest(InquiryPartInfoRequest $infoRequest): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.infoRequest = :infoRequest')
            ->setParameter('infoRequest', $infoRequest)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find messages with media items (eager loaded)
     */
    public function findByInfoRequestWithMedia(InquiryPartInfoRequest $infoRequest): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('media')
            ->leftJoin('m.mediaItems', 'media')
            ->andWhere('m.infoRequest = :infoRequest')
            ->setParameter('infoRequest', $infoRequest)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
