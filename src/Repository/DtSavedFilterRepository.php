<?php
namespace App\Repository;

use App\Entity\DtSavedFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DtSavedFilterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DtSavedFilter::class);
    }

    /** @return DtSavedFilter[] */
    public function listFor(string $tableKey, ?string $ownerId): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.tableKey = :t')->setParameter('t', $tableKey)
            ->andWhere('(f.isPublic = 1 OR f.ownerId = :u)')
            ->setParameter('u', $ownerId)
            ->orderBy('f.isPublic', 'DESC')
            ->addOrderBy('f.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
