<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

final class SqlComposerService
{
    public function __construct(private Connection $db) {}

    /** Return tables & views for a schema */
    public function listTablesAndViews(string $schema): array
    {
        $sql = "SELECT TABLE_NAME name, TABLE_TYPE type
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                ORDER BY name";
        return $this->db->fetchAllAssociative($sql, [$schema]);
    }

    /** Return columns for a table */
    public function listColumns(string $schema, string $table): array
    {
        $sql = "SELECT COLUMN_NAME name, DATA_TYPE dtype, COLUMN_TYPE ctype, IS_NULLABLE is_nullable
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";
        return $this->db->fetchAllAssociative($sql, [$schema, $table]);
    }

    /** Safe preview: block non-SELECT, add LIMIT, run */
    public function safePreview(string $sql, int $limit = 200): array
    {
        $trim = ltrim($sql);
        if (!preg_match('/^SELECT/i', $trim)) {
            throw new \RuntimeException('Only SELECT statements are allowed for preview.');
        }
        // Avoid multiple statements
        if (str_contains($sql, ';')) {
            throw new \RuntimeException('Multiple SQL statements are not allowed.');
        }
        // Append LIMIT if missing (rough detection)
        if (!preg_match('/\bLIMIT\b/i', $sql)) {
            $sql = rtrim($sql, "; \t\n\r") . "\nLIMIT " . max(1, $limit);
        }
        return $this->db->fetchAllAssociative($sql);
    }
}
