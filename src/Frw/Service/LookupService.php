<?php
// src/Frw/Service/LookupService.php
namespace App\Frw\Service;

use Doctrine\DBAL\Connection;

final class LookupService
{
    public function __construct(private Connection $db) {}

    public function get(?string $type, ?string $q): array
    {
        if (!$type) return [];
        $sql = 'SELECT code as value, label as label FROM frw_lookup WHERE type = ?';
        $args = [$type];
        if ($q) { $sql .= ' AND label LIKE ?'; $args[] = "%$q%"; }
        $sql .= ' ORDER BY label';
        return $this->db->fetchAllAssociative($sql, $args);
    }
}