<?php

namespace App\Repository;

use App\Entity\InquiryMachine;
use App\Entity\Machine;
use App\Entity\Inquiry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<InquiryMachine>
 */
class InquiryMachineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InquiryMachine::class);
    }

    /**
     * Find all machines for a specific inquiry
     */
    public function findByInquiry(Uuid|Inquiry $inquiry): array
    {
        if ($inquiry instanceof Inquiry) {
            $inquiry = $inquiry->getId();
        }

        return $this->createQueryBuilder('im')
            ->andWhere('im.inquiry = :inquiry')
            ->setParameter('inquiry', $inquiry, 'uuid')
            ->orderBy('im.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all inquiry machines for a specific machine
     */
    public function findByMachine(Uuid|Machine $machine): array
    {
        if ($machine instanceof Machine) {
            $machine = $machine->getId();
        }

        return $this->createQueryBuilder('im')
            ->andWhere('im.machine = :machine')
            ->setParameter('machine', $machine, 'uuid')
            ->orderBy('im.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the most inquired machines
     */
    public function findMostInquiredMachines(int $limit = 10): array
    {
        return $this->createQueryBuilder('im')
            ->select('IDENTITY(im.machine) as machineId', 'COUNT(im.id) as inquiryCount')
            ->join('im.inquiry', 'i')
            ->andWhere('i.status != :canceledStatus')
            ->setParameter('canceledStatus', Inquiry::STATUS_CANCELED)
            ->groupBy('im.machine')
            ->orderBy('inquiryCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Save an inquiry machine to the database
     */
    public function save(InquiryMachine $inquiryMachine, bool $flush = true): void
    {
        $this->getEntityManager()->persist($inquiryMachine);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove an inquiry machine from the database
     */
    public function remove(InquiryMachine $inquiryMachine, bool $flush = true): void
    {
        $this->getEntityManager()->remove($inquiryMachine);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
