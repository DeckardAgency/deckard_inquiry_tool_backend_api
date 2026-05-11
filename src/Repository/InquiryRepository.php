<?php

namespace App\Repository;

use App\Entity\Inquiry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Inquiry>
 *
 * @method Inquiry|null find($id, $lockMode = null, $lockVersion = null)
 * @method Inquiry|null findOneBy(array $criteria, array $orderBy = null)
 * @method Inquiry[]    findAll()
 * @method Inquiry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InquiryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inquiry::class);
    }

    /**
     * Find an inquiry by its inquiry number
     */
    public function findByInquiryNumber(string $inquiryNumber): ?Inquiry
    {
        return $this->findOneBy(['inquiryNumber' => $inquiryNumber]);
    }

    /**
     * Find inquiries by status
     *
     * @param string $status
     * @return Inquiry[]
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['createdAt' => 'DESC']);
    }

    /**
     * Find all draft inquiries for a user
     *
     * @param Uuid|User $user
     * @return Inquiry[]
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
     * Find a specific draft inquiry by ID and user
     *
     * @param Uuid $inquiryId
     * @param Uuid|User $user
     * @return Inquiry|null
     */
    public function findDraftByIdAndUser(Uuid $inquiryId, Uuid|User $user): ?Inquiry
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->findOneBy([
            'id' => $inquiryId,
            'user' => $user,
            'isDraft' => true
        ]);
    }

    /**
     * Count draft inquiries for a specific user
     */
    public function countDraftsByUser(Uuid|User $user): int
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->count(['user' => $user, 'isDraft' => true]);
    }

    /**
     * Find abandoned draft inquiries (not updated for a period)
     *
     * @param \DateTimeInterface $cutoffDate Inquiries not updated since this date
     * @return Inquiry[]
     */
    public function findAbandonedDrafts(\DateTimeInterface $cutoffDate): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.isDraft = :isDraft')
            ->andWhere('i.lastSavedAt < :cutoffDate')
            ->setParameter('isDraft', true)
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find inquiries by user
     *
     * @param Uuid|User $user
     * @return Inquiry[]
     */
    public function findByUser(Uuid|User $user): array
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Find inquiries by user and status
     *
     * @param Uuid|User $user
     * @param string $status
     * @return Inquiry[]
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
     * Find inquiries by status with pagination
     *
     * @param string $status
     * @param int $page
     * @param int $limit
     * @return Inquiry[]
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
     * Find inquiries by user with pagination
     *
     * @param Uuid|User $user
     * @param int $page
     * @param int $limit
     * @return Inquiry[]
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
     * Find recent inquiries
     *
     * @param int $limit
     * @return Inquiry[]
     */
    public function findRecentInquiries(int $limit = 10): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Find inquiries by date range
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return Inquiry[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.createdAt >= :startDate')
            ->andWhere('i.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count inquiries by status
     */
    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    /**
     * Count inquiries by user
     */
    public function countByUser(Uuid|User $user): int
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        return $this->count(['user' => $user]);
    }

    /**
     * Find inquiries that contain a specific product
     *
     * @param Uuid $productId
     * @return Inquiry[]
     */
    public function findInquiriesContainingProduct(Uuid $productId): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.items', 'item')
            ->join('item.product', 'p')
            ->andWhere('p.id = :productId')
            ->setParameter('productId', $productId, 'uuid')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Save an inquiry to the database
     */
    public function save(Inquiry $inquiry, bool $flush = true): void
    {
        $this->_em->persist($inquiry);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Remove an inquiry from the database
     */
    public function remove(Inquiry $inquiry, bool $flush = true): void
    {
        $this->_em->remove($inquiry);

        if ($flush) {
            $this->_em->flush();
        }
    }
}
