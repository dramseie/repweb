<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ReportRepository
{
    public function __construct(private Connection $db) {}

    public function insert(array $fields): int
    {
        $this->db->insert('report', $fields);
        return (int)$this->db->lastInsertId();
    }
}
