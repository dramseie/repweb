<?php

namespace App\Repository;

use App\Entity\ColetteEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ColetteEntry>
 */
class ColetteEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ColetteEntry::class);
    }

    /**
     * @return ColetteEntry[]
     */
    public function findPublished(?string $category = null): array
    {
        $qb = $this->createQueryBuilder('entry')
            ->andWhere('entry.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('entry.category', 'ASC')
            ->addOrderBy('entry.title', 'ASC');

        if ($category !== null) {
            $qb->andWhere('entry.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }
}
