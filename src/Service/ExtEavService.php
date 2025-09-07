<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

final class ExtEavService
{
    public function __construct(private Connection $db) {}

    /**
     * @return array{ci:string,name:string,status:string,tenant:string,entity_type:string}|array<string,mixed>
     */
    public function upsert(
        string $tenant, string $type, string $ci, string $name, string $status,
        array $attributes = [], ?string $updatedBy = null
    ): array {
        $json = json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \InvalidArgumentException('attributes must be JSON-serializable');
        }

        // CALL ext_eav.eav_upsert(?,?,?,?,?,?,?)
        $sql = "CALL ext_eav.eav_upsert(?, ?, ?, ?, ?, ?, ?)";
        try {
            $stmt = $this->db->executeQuery($sql, [
                $tenant, $type, $ci, $name, $status, $json, $updatedBy
            ]);
            // Procedure SELECTs a small confirmation result set at the end
            $rows = $stmt->fetchAllAssociative();
            return $rows[0] ?? [];
        } catch (DbalException $e) {
            // Surface SQLSTATE 45000 (SIGNAL) as a 400-ish application error
            $state = method_exists($e, 'getSQLState') ? $e->getSQLState() : null;
            if ($state === '45000') {
                throw new \RuntimeException($e->getMessage(), 400, $e);
            }
            throw $e;
        }
    }
}
