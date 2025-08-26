<?php

namespace App\Repository;

use App\Entity\ReportTile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportTile>
 */
class ReportTileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportTile::class);
    }

    // Add custom queries if needed
}
