<?php

namespace App\Repository;

use App\Entity\InquiryOffer;
use App\Entity\InquiryOfferItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InquiryOfferItem>
 *
 * @method InquiryOfferItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method InquiryOfferItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method InquiryOfferItem[]    findAll()
 * @method InquiryOfferItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InquiryOfferItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InquiryOfferItem::class);
    }

    /**
     * Find all items for an offer
     */
    public function findByOffer(InquiryOffer $offer): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.inquiryOffer = :offer')
            ->setParameter('offer', $offer)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
