<?php
namespace App\Repository;

use App\Entity\PosRealisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;

class PosRealisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Connection $conn)
    {
        parent::__construct($registry, PosRealisation::class);
    }

    /**
     * Return enabled réalisations from ongleri.pos_realisation ordered for display.
     * @return array<int, array{code: string, label: string}>
     */
    public function findEnabledOrdered(): array
    {
        return $this->conn->fetchAllAssociative(
            "SELECT code, label
               FROM ongleri.pos_realisation
              WHERE enabled = 1
              ORDER BY sort_order ASC, label ASC"
        );
    }
}
