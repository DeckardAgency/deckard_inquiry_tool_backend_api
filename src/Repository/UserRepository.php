<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->save($user, true);
    }

    /**
     * Find the user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Find users with orders
     *
     * @return User[]
     */
    public function findUsersWithOrders(): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.orders', 'o')
            ->groupBy('u.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with orders in a specific status
     *
     * @param string $status
     * @return User[]
     */
    public function findUsersWithOrdersByStatus(string $status): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.orders', 'o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->groupBy('u.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count orders by user
     */
    public function countOrdersByUser(Uuid|User $user): int
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        $result = $this->createQueryBuilder('u')
            ->select('COUNT(o.id) as orderCount')
            ->join('u.orders', 'o')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $user, 'uuid')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }

    /**
     * Find users registered in a date range
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return User[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most active users (with most orders)
     *
     * @param int $limit
     * @return array Array of [userId, orderCount]
     */
    public function findMostActiveUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id as userId', 'COUNT(o.id) as orderCount')
            ->join('u.orders', 'o')
            ->groupBy('u.id')
            ->orderBy('orderCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Save a user to the database
     */
    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a user from the database
     */
    public function remove(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->remove($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
