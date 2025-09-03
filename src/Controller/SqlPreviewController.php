<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SqlPreviewController extends AbstractController
{
    #[Route('/api/sql/preview', name: 'sql_preview', methods: ['POST'])]
    public function preview(Request $req, Connection $db): JsonResponse
    {
        $data   = $req->toArray();
        $sql    = trim((string)($data['sql'] ?? ''));
        $params = (array)($data['params'] ?? []);
        $limit  = (int)($data['limit'] ?? 100);

        // Only allow SELECT
        $sqlNoComments = preg_replace('/--.*?$|\/\*.*?\*\//ms', '', $sql);
        if (!preg_match('/^\s*select\b/i', $sqlNoComments ?? '')) {
            return $this->json(['error' => 'Only SELECT statements are allowed.'], 400);
        }

        // Enforce limit
        $limit = max(1, min($limit, 1000));
        $sqlTrim = rtrim($sql, " \t\n\r\0\x0B;");

        $hasLimit = preg_match('/\blimit\b/i', $sqlTrim);
        $finalSql = $hasLimit ? $sqlTrim : "SELECT * FROM ($sqlTrim) AS _sub LIMIT :__limit";

        // Build bindings — IMPORTANT: no leading ":" in array keys
        $bindParams = [];
        $types      = [];

        foreach ($params as $name => $value) {
            $bindParams[$name] = $value;
            $types[$name]      = is_int($value) ? ParameterType::INTEGER : ParameterType::STRING;
        }

        if (!$hasLimit) {
            $bindParams['__limit'] = $limit;
            $types['__limit']      = ParameterType::INTEGER;
        }

        try {
            $rows = $db->fetchAllAssociative($finalSql, $bindParams, $types);
            $cols = array_values(array_unique(array_reduce($rows, fn($c, $r) => array_merge($c, array_keys((array)$r)), [])));
            return $this->json(['columns' => $cols, 'rows' => $rows]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
