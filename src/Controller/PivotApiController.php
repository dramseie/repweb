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

    /* =========================
     * Columns + Data (existing)
     * ========================= */

    #[Route('/api/pivot/{id}/columns', name: 'api_pivot_columns', methods: ['GET'])]
    public function columns(int $id): JsonResponse
    {
        $report = $this->reports->find($id);
        if (!$report || !$report->getRepsql()) {
            return $this->json(['error' => 'Report or SQL not found'], 404);
        }
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
        $limit   = max(1, min($limit, 50000)); // safety cap

        $filter = $payload['filter'] ?? null;
        $where  = '';
        $params = [];
        if (is_array($filter) && isset($filter['column'], $filter['value'])) {
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

    /* =========================
     * Presets (report_presets)
     * ========================= */

    private function currentUserId(): string
    {
        $u = $this->getUser();
        if ($u && method_exists($u, 'getUserIdentifier')) return (string)$u->getUserIdentifier();
        if ($u && method_exists($u, 'getUsername')) return (string)$u->getUsername();
        return 'unknown';
    }

    #[Route('/api/pivot/{id}/presets', name: 'api_pivot_presets_list', methods: ['GET'])]
    public function listPresets(int $id): JsonResponse
    {
        $owner = $this->currentUserId();
        try {
            $rows = $this->db->fetchAllAssociative(
                "SELECT id, name, data, owner, created_at, updated_at
                   FROM report_presets
                  WHERE report_id = :rid
                    AND (owner = :owner OR owner IS NULL)
               ORDER BY (owner IS NULL) ASC, updated_at DESC, name ASC",
                ['rid' => $id, 'owner' => $owner]
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'DB error: '.$e->getMessage()], 500);
        }

        $items = array_map(function(array $r) {
            $data = json_decode((string)($r['data'] ?? ''), true);
            if (!is_array($data)) $data = [];
            return [
                'id'         => (string)($r['id'] ?? $r['name']), // React will use id if present
                'name'       => (string)$r['name'],
                'updated_at' => $r['updated_at'],
                'data'       => $data,
                'scope'      => $r['owner'] === null ? 'global' : 'user',
            ];
        }, $rows);

        return $this->json($items);
    }

    #[Route('/api/pivot/{id}/presets', name: 'api_pivot_presets_create', methods: ['POST'])]
    public function createPreset(int $id, Request $req): JsonResponse
    {
        $owner = $this->currentUserId();
        $body  = json_decode($req->getContent() ?: '{}', true) ?: [];
        $name  = trim((string)($body['name'] ?? ''));
        $data  = $body['data'] ?? [];

        if ($name === '') {
            return $this->json(['error' => 'Missing preset name'], 400);
        }

        try {
            // Upsert by (report_id, owner, name)
            $row = $this->db->fetchAssociative(
                "SELECT id FROM report_presets
                  WHERE report_id = :rid AND name = :name AND owner = :owner",
                ['rid' => $id, 'name' => $name, 'owner' => $owner]
            );

            if ($row) {
                $this->db->update('report_presets', [
                    'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['id' => $row['id']]);
                $presetId = (int)$row['id'];
                $status = 200;
            } else {
                $this->db->insert('report_presets', [
                    'report_id'  => $id,
                    'name'       => $name,
                    'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'owner'      => $owner,
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
                $presetId = (int)$this->db->lastInsertId();
                $status = 201;
            }
        } catch (\Throwable $e) {
            return $this->json(['error' => 'DB error: '.$e->getMessage()], 500);
        }

        return $this->json([
            'id'         => (string)$presetId,
            'name'       => $name,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'data'       => $data,
            'scope'      => 'user',
        ], $status);
    }

    #[Route('/api/pivot/{id}/presets/{presetKey}', name: 'api_pivot_presets_update', methods: ['PUT','PATCH'])]
    public function updatePreset(int $id, string $presetKey, Request $req): JsonResponse
    {
        $owner   = $this->currentUserId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $body    = json_decode($req->getContent() ?: '{}', true) ?: [];
        $newName = isset($body['name']) ? trim((string)$body['name']) : null;
        $data    = $body['data'] ?? null;

        try {
            // Find by name; prefer user preset, then (admin) global
            $row = $this->db->fetchAssociative(
                "SELECT id, owner, data
                   FROM report_presets
                  WHERE report_id = :rid
                    AND name = :key
                    AND (owner = :owner OR (:is_admin=1 AND owner IS NULL))
               ORDER BY (owner IS NULL) ASC
                  LIMIT 1",
                ['rid' => $id, 'key' => $presetKey, 'owner' => $owner, 'is_admin' => $isAdmin ? 1 : 0]
            );

            if (!$row) {
                return $this->json(['error' => 'Preset not found or not editable'], 404);
            }
            if ($row['owner'] === null && !$isAdmin) {
                return $this->json(['error' => 'Only admins can modify global presets'], 403);
            }

            $updates = ['updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')];
            if ($data !== null) $updates['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($newName !== null && $newName !== '') $updates['name'] = $newName;

            $this->db->update('report_presets', $updates, ['id' => $row['id']]);

            return $this->json([
                'id'         => (string)$row['id'],
                'name'       => $updates['name'] ?? $presetKey,
                'updated_at' => $updates['updated_at'],
                'data'       => $data ?? json_decode((string)($row['data'] ?? '[]'), true),
                'scope'      => $row['owner'] === null ? 'global' : 'user',
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'DB error: '.$e->getMessage()], 500);
        }
    }

    #[Route('/api/pivot/{id}/presets/{presetKey}', name: 'api_pivot_presets_delete', methods: ['DELETE'])]
    public function deletePreset(int $id, string $presetKey): JsonResponse
    {
        $owner   = $this->currentUserId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        try {
            $row = $this->db->fetchAssociative(
                "SELECT id, owner
                   FROM report_presets
                  WHERE report_id = :rid
                    AND name = :key
                    AND (owner = :owner OR (:is_admin=1 AND owner IS NULL))
               ORDER BY (owner IS NULL) ASC
                  LIMIT 1",
                ['rid' => $id, 'key' => $presetKey, 'owner' => $owner, 'is_admin' => $isAdmin ? 1 : 0]
            );

            if (!$row) {
                return $this->json(['error' => 'Preset not found or not deletable'], 404);
            }
            if ($row['owner'] === null && !$isAdmin) {
                return $this->json(['error' => 'Only admins can delete global presets'], 403);
            }

            $this->db->delete('report_presets', ['id' => $row['id']]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'DB error: '.$e->getMessage()], 500);
        }

        return $this->json(['ok' => true]);
    }
}
