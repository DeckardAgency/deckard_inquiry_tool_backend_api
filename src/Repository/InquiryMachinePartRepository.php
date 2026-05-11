<?php

namespace App\Repository;

use App\Entity\InquiryMachinePart;
use App\Entity\InquiryMachine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<InquiryMachinePart>
 */
class InquiryMachinePartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InquiryMachinePart::class);
    }

    /**
     * Find all parts for a specific inquiry machine
     */
    public function findByInquiryMachine(Uuid|InquiryMachine $inquiryMachine): array
    {
        if ($inquiryMachine instanceof InquiryMachine) {
            $inquiryMachine = $inquiryMachine->getId();
        }

        return $this->createQueryBuilder('imp')
            ->andWhere('imp.inquiryMachine = :inquiryMachine')
            ->setParameter('inquiryMachine', $inquiryMachine, 'uuid')
            ->orderBy('imp.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find parts by part name (partial match)
     */
    public function findByPartName(string $partName): array
    {
        return $this->createQueryBuilder('imp')
            ->andWhere('imp.partName LIKE :partName')
            ->setParameter('partName', '%' . $partName . '%')
            ->orderBy('imp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find parts by part number (exact match)
     */
    public function findByPartNumber(string $partNumber): array
    {
        return $this->createQueryBuilder('imp')
            ->andWhere('imp.partNumber = :partNumber')
            ->setParameter('partNumber', $partNumber)
            ->orderBy('imp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Save an inquiry machine part to the database
     */
    public function save(InquiryMachinePart $part, bool $flush = true): void
    {
        $this->getEntityManager()->persist($part);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove an inquiry machine part from the database
     */
    public function remove(InquiryMachinePart $part, bool $flush = true): void
    {
        $this->getEntityManager()->remove($part);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
