<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserInvitation>
 */
class UserInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInvitation::class);
    }

    /**
     * Find an invitation by its token
     */
    public function findByToken(string $token): ?UserInvitation
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Find a pending invitation by email address
     */
    public function findPendingByEmail(string $email): ?UserInvitation
    {
        return $this->createQueryBuilder('ui')
            ->where('ui.email = :email')
            ->andWhere('ui.status = :status')
            ->setParameter('email', $email)
            ->setParameter('status', UserInvitation::STATUS_PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all invitations created by a specific user
     *
     * @return UserInvitation[]
     */
    public function findByCreatedBy(User $user): array
    {
        return $this->createQueryBuilder('ui')
            ->where('ui.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('ui.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all expired invitations
     *
     * @return UserInvitation[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('ui')
            ->where('ui.expiresAt < :now')
            ->andWhere('ui.status = :status')
            ->setParameter('now', new \DateTime())
            ->setParameter('status', UserInvitation::STATUS_PENDING)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all pending invitations
     *
     * @return UserInvitation[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('ui')
            ->where('ui.status = :status')
            ->andWhere('ui.expiresAt > :now')
            ->setParameter('status', UserInvitation::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->orderBy('ui.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all completed invitations
     *
     * @return UserInvitation[]
     */
    public function findCompleted(): array
    {
        return $this->createQueryBuilder('ui')
            ->where('ui.status = :status')
            ->setParameter('status', UserInvitation::STATUS_COMPLETED)
            ->orderBy('ui.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count invitations by status
     */
    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('ui')
            ->select('COUNT(ui.id)')
            ->where('ui.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get completion rate (completed / total sent)
     */
    public function getCompletionRate(): float
    {
        $total = $this->count([]);

        if ($total === 0) {
            return 0.0;
        }

        $completed = $this->countByStatus(UserInvitation::STATUS_COMPLETED);

        return ($completed / $total) * 100;
    }

    /**
     * Find invitations that are about to expire (within X days)
     *
     * @return UserInvitation[]
     */
    public function findExpiringWithin(int $days = 1): array
    {
        $now = new \DateTime();
        $threshold = new \DateTime(sprintf('+%d days', $days));

        return $this->createQueryBuilder('ui')
            ->where('ui.status = :status')
            ->andWhere('ui.expiresAt > :now')
            ->andWhere('ui.expiresAt <= :threshold')
            ->setParameter('status', UserInvitation::STATUS_PENDING)
            ->setParameter('now', $now)
            ->setParameter('threshold', $threshold)
            ->orderBy('ui.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if email has any active (pending, non-expired) invitation
     */
    public function hasActiveInvitation(string $email): bool
    {
        $count = (int) $this->createQueryBuilder('ui')
            ->select('COUNT(ui.id)')
            ->where('ui.email = :email')
            ->andWhere('ui.status = :status')
            ->andWhere('ui.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('status', UserInvitation::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Delete old completed/expired invitations (for cleanup)
     */
    public function deleteOldInvitations(int $daysOld = 30): int
    {
        $threshold = new \DateTime(sprintf('-%d days', $daysOld));

        return $this->createQueryBuilder('ui')
            ->delete()
            ->where('ui.status IN (:statuses)')
            ->andWhere('ui.updatedAt < :threshold')
            ->setParameter('statuses', [
                UserInvitation::STATUS_COMPLETED,
                UserInvitation::STATUS_EXPIRED,
                UserInvitation::STATUS_REVOKED
            ])
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
