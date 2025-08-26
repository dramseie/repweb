<?php
namespace App\Controller;

use App\Repository\ReportRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PivotApiController extends AbstractController
{
    public function __construct(
        private ReportRepository $reports,
        private Connection $db,
    ) {}

    #[Route('/api/pivot/{id}/columns', name: 'api_pivot_columns', methods: ['GET'])]
    public function columns(int $id): JsonResponse
    {
        $report = $this->reports->find($id);
        if (!$report || !$report->getRepsql()) {
            return $this->json(['error' => 'Report or SQL not found'], 404);
        }

        // LIMIT 1 to infer columns from first row
        $sql = sprintf('SELECT * FROM (%s) t LIMIT 1', $report->getRepsql());
        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'SQL error: '.$e->getMessage()], 400);
        }

        $first = $rows[0] ?? [];
        $columns = [];
        foreach (array_keys($first) as $key) {
            $columns[] = [
                'key'   => $key,
                'title' => ucwords(str_replace(['_', '-'], ' ', (string)$key)),
                'type'  => null,
            ];
        }

        return $this->json($columns);
    }

    #[Route('/api/pivot/{id}/data', name: 'api_pivot_data', methods: ['POST'])]
    public function data(int $id, Request $request): JsonResponse
    {
        $report = $this->reports->find($id);
        if (!$report || !$report->getRepsql()) {
            return $this->json(['error' => 'Report or SQL not found'], 404);
        }

        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $limit   = (int)($payload['limit'] ?? 10000);
        $limit   = max(1, min($limit, 50000)); // hard cap to keep it safe

        // Optional very basic server-side filter (safe allowlist for a single column equality)
        // Example body: { "filter": { "column": "status", "value": "ACTIVE" } }
        $filter = $payload['filter'] ?? null;
        $where  = '';
        $params = [];
        if (is_array($filter) && isset($filter['column'], $filter['value'])) {
            // very simple column name allowlist: alnum, underscore only
            if (preg_match('/^[A-Za-z0-9_]+$/', $filter['column'])) {
                $where = ' WHERE '.$filter['column'].' = :_fval';
                $params['_fval'] = $filter['value'];
            }
        }

        $sql = sprintf('SELECT * FROM (%s) t%s LIMIT %d', $report->getRepsql(), $where, $limit);

        try {
            $rows = $this->db->fetchAllAssociative($sql, $params);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'SQL error: '.$e->getMessage()], 400);
        }

        return $this->json(['data' => $rows, 'limit' => $limit, 'count' => count($rows)]);
    }
}
