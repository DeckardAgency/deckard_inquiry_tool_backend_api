<?php

namespace App\Repository;

use App\Entity\SupportTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupportTicket>
 */
class SupportTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportTicket::class);
    }

    /**
     * Find support tickets by user
     *
     * @return SupportTicket[]
     */
    public function findByUser($user): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.user = :user')
            ->setParameter('user', $user)
            ->orderBy('st.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find open tickets
     *
     * @return SupportTicket[]
     */
    public function findOpenTickets(): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.status IN (:statuses)')
            ->setParameter('statuses', ['open', 'in_progress'])
            ->orderBy('st.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count tickets by status
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->andWhere('st.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
