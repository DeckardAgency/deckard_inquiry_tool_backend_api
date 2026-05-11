<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Find client by code
     */
    public function findByCode(string $code): ?Client
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Find client by user
     */
    public function findByUser(User $user): ?Client
    {
        return $user->getClient();
    }

    /**
     * Find clients with custom product prices
     *
     * @return Client[]
     */
    public function findClientsWithCustomPrices(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.productPrices', 'pp')
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find clients by date range
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return Client[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.createdAt >= :startDate')
            ->andWhere('c.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Save a client to the database
     */
    public function save(Client $client, bool $flush = true): void
    {
        $this->getEntityManager()->persist($client);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a client from the database
     */
    public function remove(Client $client, bool $flush = true): void
    {
        $this->getEntityManager()->remove($client);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
