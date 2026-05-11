<?php

namespace App\Repository;

use App\Entity\Area;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Area>
 */
class AreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Area::class);
    }

    /**
     * Find all active areas for a client
     *
     * @return Area[]
     */
    public function findActiveByClient(Client $client): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.client = :client')
            ->andWhere('a.isActive = :active')
            ->setParameter('client', $client)
            ->setParameter('active', true)
            ->orderBy('a.priority', 'DESC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find root areas (no parent) for a client
     *
     * @return Area[]
     */
    public function findRootAreasByClient(Client $client): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.client = :client')
            ->andWhere('a.parentArea IS NULL')
            ->andWhere('a.isActive = :active')
            ->setParameter('client', $client)
            ->setParameter('active', true)
            ->orderBy('a.priority', 'DESC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find area by code and client
     */
    public function findOneByCodeAndClient(string $code, Client $client): ?Area
    {
        return $this->createQueryBuilder('a')
            ->where('a.code = :code')
            ->andWhere('a.client = :client')
            ->setParameter('code', strtoupper($code))
            ->setParameter('client', $client)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all child areas (recursive)
     *
     * @return Area[]
     */
    public function findAllDescendants(Area $area): array
    {
        $descendants = [];
        $this->collectDescendants($area, $descendants);
        return $descendants;
    }

    private function collectDescendants(Area $area, array &$descendants): void
    {
        foreach ($area->getChildAreas() as $child) {
            $descendants[] = $child;
            $this->collectDescendants($child, $descendants);
        }
    }

    /**
     * Find areas with available managers
     *
     * @return Area[]
     */
    public function findAreasWithAvailableManagers(Client $client): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.areaManagers', 'am')
            ->innerJoin('am.manager', 'm')
            ->where('a.client = :client')
            ->andWhere('a.isActive = :active')
            ->andWhere('am.isActive = :active')
            ->andWhere('m.isActive = :active')
            ->setParameter('client', $client)
            ->setParameter('active', true)
            ->groupBy('a.id')
            ->orderBy('a.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
