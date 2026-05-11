<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ClientProductPrice;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientProductPrice>
 *
 * @method ClientProductPrice|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientProductPrice|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientProductPrice[]    findAll()
 * @method ClientProductPrice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientProductPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientProductPrice::class);
    }

    /**
     * Find a custom price for a specific client and product
     */
    public function findCustomPrice(Client $client, Product $product): ?ClientProductPrice
    {
        return $this->findOneBy([
            'client' => $client,
            'product' => $product
        ]);
    }

    /**
     * Find all valid custom prices for a specific client
     *
     * @param Client $client
     * @return ClientProductPrice[]
     */
    public function findValidPricesForClient(Client $client): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('cpp')
            ->andWhere('cpp.client = :client')
            ->andWhere('(cpp.validFrom IS NULL OR cpp.validFrom <= :now)')
            ->andWhere('(cpp.validUntil IS NULL OR cpp.validUntil >= :now)')
            ->setParameter('client', $client)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all custom prices for a product
     *
     * @param Product $product
     * @return ClientProductPrice[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->findBy(['product' => $product]);
    }

    /**
     * Find all expired custom prices
     *
     * @return ClientProductPrice[]
     */
    public function findExpiredPrices(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('cpp')
            ->andWhere('cpp.validUntil IS NOT NULL')
            ->andWhere('cpp.validUntil < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Save a custom price to the database
     */
    public function save(ClientProductPrice $clientProductPrice, bool $flush = true): void
    {
        $this->getEntityManager()->persist($clientProductPrice);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a custom price from the database
     */
    public function remove(ClientProductPrice $clientProductPrice, bool $flush = true): void
    {
        $this->getEntityManager()->remove($clientProductPrice);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
