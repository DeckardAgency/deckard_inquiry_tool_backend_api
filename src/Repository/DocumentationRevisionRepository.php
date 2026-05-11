<?php

namespace App\Repository;

use App\Entity\Documentation;
use App\Entity\DocumentationRevision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentationRevision>
 */
class DocumentationRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentationRevision::class);
    }

    /**
     * Find all revisions for a documentation ordered by revision number descending
     *
     * @return DocumentationRevision[]
     */
    public function findByDocumentation(Documentation $documentation): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.documentation = :documentation')
            ->setParameter('documentation', $documentation)
            ->orderBy('r.revisionNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the latest revision number for a documentation
     */
    public function getLatestRevisionNumber(Documentation $documentation): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('MAX(r.revisionNumber)')
            ->andWhere('r.documentation = :documentation')
            ->setParameter('documentation', $documentation)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Find a specific revision by documentation and revision number
     */
    public function findByDocumentationAndRevisionNumber(Documentation $documentation, int $revisionNumber): ?DocumentationRevision
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.documentation = :documentation')
            ->andWhere('r.revisionNumber = :revisionNumber')
            ->setParameter('documentation', $documentation)
            ->setParameter('revisionNumber', $revisionNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
