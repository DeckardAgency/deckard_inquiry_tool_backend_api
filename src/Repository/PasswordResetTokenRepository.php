<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * Find a token by its value
     */
    public function findByToken(string $token): ?PasswordResetToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Find a valid (pending and not expired) token by its value
     */
    public function findValidToken(string $token): ?PasswordResetToken
    {
        return $this->createQueryBuilder('prt')
            ->where('prt.token = :token')
            ->andWhere('prt.status = :status')
            ->andWhere('prt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('status', PasswordResetToken::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active tokens for a user
     *
     * @return PasswordResetToken[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('prt')
            ->where('prt.user = :user')
            ->andWhere('prt.status = :status')
            ->andWhere('prt.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('status', PasswordResetToken::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->orderBy('prt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count recent reset requests for a user (rate limiting)
     */
    public function countRecentByUser(User $user, int $hours = 1): int
    {
        $since = new \DateTime(sprintf('-%d hours', $hours));

        return (int) $this->createQueryBuilder('prt')
            ->select('COUNT(prt.id)')
            ->where('prt.user = :user')
            ->andWhere('prt.createdAt > :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Revoke all pending tokens for a user
     */
    public function revokeAllForUser(User $user): int
    {
        return $this->createQueryBuilder('prt')
            ->update()
            ->set('prt.status', ':revokedStatus')
            ->where('prt.user = :user')
            ->andWhere('prt.status = :pendingStatus')
            ->setParameter('user', $user)
            ->setParameter('revokedStatus', PasswordResetToken::STATUS_REVOKED)
            ->setParameter('pendingStatus', PasswordResetToken::STATUS_PENDING)
            ->getQuery()
            ->execute();
    }

    /**
     * Find all expired tokens (for cleanup)
     *
     * @return PasswordResetToken[]
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('prt')
            ->where('prt.expiresAt < :now')
            ->andWhere('prt.status = :status')
            ->setParameter('now', new \DateTime())
            ->setParameter('status', PasswordResetToken::STATUS_PENDING)
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark expired tokens as expired
     */
    public function markExpiredTokens(): int
    {
        return $this->createQueryBuilder('prt')
            ->update()
            ->set('prt.status', ':expiredStatus')
            ->where('prt.expiresAt < :now')
            ->andWhere('prt.status = :pendingStatus')
            ->setParameter('expiredStatus', PasswordResetToken::STATUS_EXPIRED)
            ->setParameter('pendingStatus', PasswordResetToken::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Delete old tokens (for cleanup)
     */
    public function deleteOldTokens(int $daysOld = 30): int
    {
        $threshold = new \DateTime(sprintf('-%d days', $daysOld));

        return $this->createQueryBuilder('prt')
            ->delete()
            ->where('prt.status IN (:statuses)')
            ->andWhere('prt.createdAt < :threshold')
            ->setParameter('statuses', [
                PasswordResetToken::STATUS_USED,
                PasswordResetToken::STATUS_EXPIRED,
                PasswordResetToken::STATUS_REVOKED
            ])
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    /**
     * Get statistics about password reset tokens
     */
    public function getStatistics(): array
    {
        $total = $this->count([]);
        $pending = $this->count(['status' => PasswordResetToken::STATUS_PENDING]);
        $used = $this->count(['status' => PasswordResetToken::STATUS_USED]);
        $expired = $this->count(['status' => PasswordResetToken::STATUS_EXPIRED]);
        $revoked = $this->count(['status' => PasswordResetToken::STATUS_REVOKED]);

        return [
            'total' => $total,
            'pending' => $pending,
            'used' => $used,
            'expired' => $expired,
            'revoked' => $revoked,
            'usage_rate' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get all password reset tokens for a user with history
     *
     * @return PasswordResetToken[]
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('prt')
            ->leftJoin('prt.createdBy', 'cb')
            ->addSelect('cb')
            ->where('prt.user = :user')
            ->setParameter('user', $user)
            ->orderBy('prt.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
