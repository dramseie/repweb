<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/smartsheet/task-dependencies', name: 'api_smartsheet_task_dependencies_')]
class SmartsheetTaskDependencyController extends AbstractController
{
    private const TASK_SOURCE = 'nifi.smartsheet_master_data';
    private const WORKSPACE_TABLE = 'smartsheet_task_dependency';
    private const NODE_TABLE = 'smartsheet_task_dependency_node';
    private const EDGE_TABLE = 'smartsheet_task_dependency_edge';

    public function __construct(private readonly Connection $connection) {}

    #[Route('/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(): JsonResponse
    {
                $sql = sprintf(
                        "SELECT task_name
                        FROM %s
                        WHERE IFNULL(phase, '') NOT IN ('Store', 'Country')
                            AND task_name IS NOT NULL
                            AND task_name <> ''
                        GROUP BY task_name
                        ORDER BY MIN(row_num), task_name",
                        self::TASK_SOURCE
                );
                $rows = $this->connection->fetchFirstColumn($sql);
        return $this->json($rows);
    }

    #[Route('/workspaces', name: 'workspaces_list', methods: ['GET'])]
    public function workspaces(): JsonResponse
    {
        $sql = sprintf(
            'SELECT id, name, notes, created_by, created_at, updated_at FROM %s ORDER BY name',
            self::WORKSPACE_TABLE
        );
        $rows = $this->connection->fetchAllAssociative($sql);
        return $this->json($rows);
    }

    #[Route('/workspaces', name: 'workspaces_create', methods: ['POST'])]
    public function createWorkspace(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->json(['error' => 'Workspace name is required.'], 400);
        }

        $notes = $payload['notes'] ?? null;
        $userRef = $this->getUser()?->getUserIdentifier();

        $this->connection->insert(self::WORKSPACE_TABLE, [
            'name' => $name,
            'notes' => $notes,
            'created_by' => $userRef,
        ], [
            'notes' => ParameterType::STRING,
            'created_by' => ParameterType::STRING,
        ]);

        $id = (int) $this->connection->lastInsertId();
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT id, name, notes, created_by, created_at, updated_at FROM %s WHERE id = ?', self::WORKSPACE_TABLE),
            [$id]
        );

        return $this->json($row ?: ['id' => $id, 'name' => $name]);
    }

    #[Route('/graph', name: 'graph', methods: ['GET'])]
    public function graph(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->query->get('workspaceId', 0);
        if ($workspaceId <= 0) {
            return $this->json(['error' => 'workspaceId is required.'], 400);
        }

        $nodes = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, task_name, pos_x, pos_y FROM %s WHERE workspace_id = ? ORDER BY id', self::NODE_TABLE),
            [$workspaceId]
        );
        $edges = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, source_node_id, target_node_id, name, duration FROM %s WHERE workspace_id = ? ORDER BY id', self::EDGE_TABLE),
            [$workspaceId]
        );

        $nodePayload = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'task_name' => $row['task_name'],
                'position' => [
                    'x' => (float) $row['pos_x'],
                    'y' => (float) $row['pos_y'],
                ],
            ];
        }, $nodes);

        $edgePayload = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'source_node_id' => (int) $row['source_node_id'],
                'target_node_id' => (int) $row['target_node_id'],
                'name' => $row['name'] ?? null,
                'duration' => $row['duration'] !== null ? (int) $row['duration'] : null,
            ];
        }, $edges);

        return $this->json(['nodes' => $nodePayload, 'edges' => $edgePayload]);
    }

    #[Route('/node', name: 'node_create', methods: ['POST'])]
    public function createNode(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $workspaceId = (int) ($payload['workspaceId'] ?? 0);
        $taskName = trim((string) ($payload['taskName'] ?? ''));
        $position = $payload['position'] ?? null;

        if ($workspaceId <= 0 || $taskName === '') {
            return $this->json(['error' => 'workspaceId and taskName are required.'], 400);
        }

        $posX = is_array($position) ? (float) ($position['x'] ?? 0) : 0.0;
        $posY = is_array($position) ? (float) ($position['y'] ?? 0) : 0.0;

        $existing = $this->connection->fetchAssociative(
            sprintf('SELECT id FROM %s WHERE workspace_id = ? AND task_name = ?', self::NODE_TABLE),
            [$workspaceId, $taskName]
        );

        if ($existing) {
            $id = (int) $existing['id'];
            $this->connection->update(
                self::NODE_TABLE,
                ['pos_x' => $posX, 'pos_y' => $posY],
                ['id' => $id]
            );
        } else {
            $this->connection->insert(self::NODE_TABLE, [
                'workspace_id' => $workspaceId,
                'task_name' => $taskName,
                'pos_x' => $posX,
                'pos_y' => $posY,
            ]);
            $id = (int) $this->connection->lastInsertId();
        }

        return $this->json([
            'id' => $id,
            'task_name' => $taskName,
            'position' => ['x' => $posX, 'y' => $posY],
        ]);
    }

    #[Route('/node/{id}', name: 'node_update', methods: ['PATCH'])]
    public function updateNode(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $position = $payload['position'] ?? null;

        if (is_array($position)) {
            $this->connection->update(
                self::NODE_TABLE,
                [
                    'pos_x' => (float) ($position['x'] ?? 0),
                    'pos_y' => (float) ($position['y'] ?? 0),
                ],
                ['id' => $id]
            );
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/node/{id}', name: 'node_delete', methods: ['DELETE'])]
    public function deleteNode(int $id): JsonResponse
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->delete(self::EDGE_TABLE, ['source_node_id' => $id]);
            $this->connection->delete(self::EDGE_TABLE, ['target_node_id' => $id]);
            $this->connection->delete(self::NODE_TABLE, ['id' => $id]);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            return $this->json(['error' => $e->getMessage()], 500);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/edge', name: 'edge_create', methods: ['POST'])]
    public function createEdge(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $workspaceId = (int) ($payload['workspaceId'] ?? 0);
        $sourceId = (int) ($payload['sourceId'] ?? 0);
        $targetId = (int) ($payload['targetId'] ?? 0);
        $name = isset($payload['name']) ? trim((string) $payload['name']) : null;
        $durationRaw = $payload['duration'] ?? null;
        $duration = $durationRaw === null || $durationRaw === '' ? null : (int) $durationRaw;

        if ($workspaceId <= 0 || $sourceId <= 0 || $targetId <= 0) {
            return $this->json(['error' => 'workspaceId, sourceId, and targetId are required.'], 400);
        }

        $existing = $this->connection->fetchAssociative(
            sprintf('SELECT id FROM %s WHERE workspace_id = ? AND source_node_id = ? AND target_node_id = ?', self::EDGE_TABLE),
            [$workspaceId, $sourceId, $targetId]
        );

        if ($existing) {
            return $this->json(['id' => (int) $existing['id']]);
        }

        $this->connection->insert(self::EDGE_TABLE, [
            'workspace_id' => $workspaceId,
            'source_node_id' => $sourceId,
            'target_node_id' => $targetId,
            'name' => $name !== '' ? $name : null,
            'duration' => $duration,
        ]);

        return $this->json([
            'id' => (int) $this->connection->lastInsertId(),
            'name' => $name !== '' ? $name : null,
            'duration' => $duration,
        ]);
    }

    #[Route('/edge/{id}', name: 'edge_update', methods: ['PATCH'])]
    public function updateEdge(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : null;
        $durationRaw = $payload['duration'] ?? null;
        $duration = $durationRaw === null || $durationRaw === '' ? null : (int) $durationRaw;

        $fields = [];
        if (array_key_exists('name', $payload)) {
            $fields['name'] = $name !== '' ? $name : null;
        }
        if (array_key_exists('duration', $payload)) {
            $fields['duration'] = $duration;
        }

        if ($fields) {
            $this->connection->update(self::EDGE_TABLE, $fields, ['id' => $id]);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/edge/{id}', name: 'edge_delete', methods: ['DELETE'])]
    public function deleteEdge(int $id): JsonResponse
    {
        $this->connection->delete(self::EDGE_TABLE, ['id' => $id]);
        return $this->json(['ok' => true]);
    }

    #[Route('/layout/save', name: 'layout_save', methods: ['POST'])]
    public function saveLayout(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $workspaceId = (int) ($payload['workspaceId'] ?? 0);
        $nodes = $payload['nodes'] ?? [];

        if ($workspaceId <= 0 || !is_array($nodes)) {
            return $this->json(['error' => 'workspaceId and nodes are required.'], 400);
        }

        $this->connection->beginTransaction();
        try {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $id = (int) ($node['id'] ?? 0);
                $pos = $node['position'] ?? null;
                if ($id <= 0 || !is_array($pos)) {
                    continue;
                }
                $this->connection->update(
                    self::NODE_TABLE,
                    [
                        'pos_x' => (float) ($pos['x'] ?? 0),
                        'pos_y' => (float) ($pos['y'] ?? 0),
                    ],
                    [
                        'id' => $id,
                        'workspace_id' => $workspaceId,
                    ]
                );
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            return $this->json(['error' => $e->getMessage()], 500);
        }

        return $this->json(['ok' => true]);
    }
}
