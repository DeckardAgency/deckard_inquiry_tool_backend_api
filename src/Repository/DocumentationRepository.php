<?php

namespace App\Repository;

use App\Entity\Documentation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Documentation>
 */
class DocumentationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Documentation::class);
    }

    /**
     * Find documentation by slug
     */
    public function findBySlug(string $slug): ?Documentation
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all published documentation ordered by sortOrder
     *
     * @return Documentation[]
     */
    public function findAllPublished(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('d.sortOrder', 'ASC')
            ->addOrderBy('d.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documentation by category
     *
     * @return Documentation[]
     */
    public function findByCategory(string $category, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.category = :category')
            ->setParameter('category', $category)
            ->orderBy('d.sortOrder', 'ASC')
            ->addOrderBy('d.title', 'ASC');

        if ($publishedOnly) {
            $qb->andWhere('d.isPublished = :published')
               ->setParameter('published', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all unique categories
     *
     * @return string[]
     */
    public function findAllCategories(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('DISTINCT d.category')
            ->andWhere('d.category IS NOT NULL')
            ->orderBy('d.category', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'category');
    }
}
