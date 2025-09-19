<?php
namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;

/**
 * @extends ServiceEntityRepository<Report>
 */
final class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Connection $db)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * Insert a report row using DBAL, returns new repid
     */
    public function insert(array $fields): int
    {
        $this->db->insert('report', $fields);
        return (int) $this->db->lastInsertId();
    }
}
