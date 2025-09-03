<?php
namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/sqlc', name: 'api_sqlc_')]
class SqlComposerController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('/schema', name: 'schema', methods: ['GET'])]
    public function schema(Request $req): JsonResponse
    {
        $schema = $req->query->get('schema') ?: $this->db->getDatabase();

        // Tables
        $tables = $this->db->fetchAllAssociative(
            'SELECT TABLE_NAME as table_name, TABLE_TYPE as table_type
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME',
            [$schema]
        );

        // Columns
        $cols = $this->db->fetchAllAssociative(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$schema]
        );

        // Foreign keys
        $fks = $this->db->fetchAllAssociative(
            'SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.CONSTRAINT_NAME,
                    kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
               ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
              AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
             WHERE kcu.TABLE_SCHEMA = ? AND tc.CONSTRAINT_TYPE = "FOREIGN KEY"',
            [$schema]
        );

        return $this->json([
            'schema'       => $schema,
            'tables'       => $tables,
            'columns'      => $cols,
            'foreign_keys' => $fks,
        ]);
    }

    #[Route('/preview', name: 'preview', methods: ['POST'])]
    public function preview(Request $req): JsonResponse
    {
        $payload = json_decode($req->getContent() ?: '{}', true);
        $sql = trim($payload['sql'] ?? '');

        if ($sql === '' || strncasecmp($sql, 'SELECT', 6) !== 0) {
            return $this->json(['error' => 'Only SELECT statements are allowed.'], 400);
        }

        // Clamp limit for safety
        $limit = (int)($payload['limit'] ?? 100);
        if ($limit < 1 || $limit > 1000) {
            $limit = 100;
        }

        // Try to detect if a LIMIT already exists; if not, append
        $hasLimit = (bool)preg_match('/\bLIMIT\b/i', $sql);
        $safeSql = $sql . ($hasLimit ? '' : "\nLIMIT " . $limit);

        // Execute in a read-only transaction
        $this->db->beginTransaction();
        try {
            $rows = $this->db->fetchAllAssociative($safeSql);
            $this->db->rollBack(); // nothing to commit
        } catch (\Throwable $e) {
            if ($this->db->isTransactionActive()) {
                $this->db->rollBack();
            }
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json(['rows' => $rows]);
    }
	
	#[Route('/schemata', name: 'schemata', methods: ['GET'])]
	public function schemata(): JsonResponse
	{
		$rows = $this->db->fetchAllAssociative(
			'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA ORDER BY SCHEMA_NAME'
		);
		return $this->json(['schemata' => $rows]);
	}
	
	
}
