<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ClientMachineInstalledBase;
use App\Entity\Machine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientMachineInstalledBase>
 */
class ClientMachineInstalledBaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientMachineInstalledBase::class);
    }

    public function findMachinesForClient(
        Client $client,
        int $page = 1,
        int $limit = 50,
        ?string $status = null,
        ?string $location = null
    ): Query {
        $qb = $this->createQueryBuilder('ib')
            ->leftJoin('ib.machine', 'm')
            ->addSelect('m')
            ->where('ib.client = :client')
            ->setParameter('client', $client)
            ->orderBy('ib.installedDate', 'DESC');

        if ($status) {
            $qb->andWhere('ib.status = :status')
                ->setParameter('status', $status);
        }

        if ($location) {
            $qb->andWhere('ib.location LIKE :location')
                ->setParameter('location', '%' . $location . '%');
        }

        return $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();
    }

    public function findClientsForMachine(
        Machine $machine,
        int $page = 1,
        int $limit = 50,
        ?string $status = null
    ): Query {
        $qb = $this->createQueryBuilder('ib')
            ->leftJoin('ib.client', 'c')
            ->addSelect('c')
            ->where('ib.machine = :machine')
            ->setParameter('machine', $machine)
            ->orderBy('ib.installedDate', 'DESC');

        if ($status) {
            $qb->andWhere('ib.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();
    }

    public function countMachinesForClient(Client $client, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('ib')
            ->select('COUNT(ib.id)')
            ->where('ib.client = :client')
            ->setParameter('client', $client);

        if ($status) {
            $qb->andWhere('ib.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countClientsForMachine(Machine $machine, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('ib')
            ->select('COUNT(ib.id)')
            ->where('ib.machine = :machine')
            ->setParameter('machine', $machine);

        if ($status) {
            $qb->andWhere('ib.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getClientStatistics(Client $client): array
    {
        $qb = $this->createQueryBuilder('ib')
            ->select([
                'COUNT(ib.id) as totalMachines',
                'COUNT(CASE WHEN ib.status = \'active\' THEN 1 END) as activeMachines',
                'COUNT(CASE WHEN ib.status = \'maintenance\' THEN 1 END) as maintenanceMachines',
                'COUNT(CASE WHEN ib.warrantyEndDate > :now THEN 1 END) as underWarranty',
                'SUM(ib.monthlyRate) as totalMonthlyRate'
            ])
            ->where('ib.client = :client')
            ->setParameter('client', $client)
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->getSingleResult();
    }

    public function findMachinesUnderWarrantyForClient(Client $client): Query
    {
        return $this->createQueryBuilder('ib')
            ->leftJoin('ib.machine', 'm')
            ->addSelect('m')
            ->where('ib.client = :client')
            ->andWhere('ib.warrantyEndDate > :now')
            ->andWhere('ib.status = :active')
            ->setParameter('client', $client)
            ->setParameter('now', new \DateTime())
            ->setParameter('active', 'active')
            ->orderBy('ib.warrantyEndDate', 'ASC')
            ->getQuery();
    }

    public function batchUpdateStatus(array $relationIds, string $newStatus): int
    {
        return $this->createQueryBuilder('ib')
            ->update()
            ->set('ib.status', ':status')
            ->set('ib.updatedAt', ':now')
            ->where('ib.id IN (:ids)')
            ->setParameter('status', $newStatus)
            ->setParameter('now', new \DateTime())
            ->setParameter('ids', $relationIds)
            ->getQuery()
            ->execute();
    }
}
