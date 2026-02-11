<?php

declare(strict_types=1);

namespace App\Controller\Api;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/smartsheet/task-manager', name: 'api_smartsheet_task_manager_')]
class SmartsheetTaskManagerController extends AbstractController
{
    private const MASTER_TABLE = 'nifi.smartsheet_master_data';
    private const TASK_TABLE = 'smartsheet_task_manager';

    public function __construct(private readonly Connection $connection) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->seedFromMaster();

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, task_name, parent_id, sort_order FROM %s ORDER BY parent_id IS NOT NULL, parent_id, sort_order, id', self::TASK_TABLE)
        );

        return $this->json(['items' => $rows]);
    }

    #[Route('/task', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $taskName = trim((string) ($payload['taskName'] ?? ''));
        $parentIdRaw = $payload['parentId'] ?? null;
        $parentId = $parentIdRaw === '' || $parentIdRaw === null ? null : (int) $parentIdRaw;
        $sortOrder = $payload['sortOrder'] ?? null;

        if ($taskName === '') {
            return $this->json(['error' => 'taskName is required.'], 400);
        }

        if ($parentId !== null && !$this->taskExists($parentId)) {
            return $this->json(['error' => 'parentId not found.'], 400);
        }

        if ($sortOrder === null) {
            $sortOrder = (int) $this->connection->fetchOne(
                sprintf('SELECT COALESCE(MAX(sort_order), 0) FROM %s WHERE parent_id <=> :parent_id', self::TASK_TABLE),
                ['parent_id' => $parentId],
                ['parent_id' => $parentId === null ? ParameterType::NULL : ParameterType::INTEGER]
            ) + 1;
        }

        $now = new DateTimeImmutable();
        $this->connection->insert(self::TASK_TABLE, [
            'task_name' => $taskName,
            'parent_id' => $parentId,
            'sort_order' => (int) $sortOrder,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->connection->lastInsertId();

        return $this->json([
            'id' => $id,
            'task_name' => $taskName,
            'parent_id' => $parentId,
            'sort_order' => (int) $sortOrder,
        ]);
    }

    #[Route('/task/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->taskExists($id)) {
            return $this->json(['error' => 'Task not found.'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $fields = [];

        if (array_key_exists('taskName', $payload)) {
            $taskName = trim((string) $payload['taskName']);
            if ($taskName === '') {
                return $this->json(['error' => 'taskName is required.'], 400);
            }
            $fields['task_name'] = $taskName;
        }

        if (array_key_exists('parentId', $payload)) {
            $parentIdRaw = $payload['parentId'];
            $parentId = $parentIdRaw === '' || $parentIdRaw === null ? null : (int) $parentIdRaw;
            if ($parentId === $id) {
                return $this->json(['error' => 'parentId cannot be the task itself.'], 400);
            }
            if ($parentId !== null && !$this->taskExists($parentId)) {
                return $this->json(['error' => 'parentId not found.'], 400);
            }
            if ($parentId !== null && $this->isDescendant($parentId, $id)) {
                return $this->json(['error' => 'parentId cannot be a descendant of the task.'], 400);
            }
            $fields['parent_id'] = $parentId;
        }

        if (array_key_exists('sortOrder', $payload)) {
            $fields['sort_order'] = (int) $payload['sortOrder'];
        }

        if (empty($fields)) {
            return $this->json(['error' => 'No updates provided.'], 400);
        }

        $fields['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->update(self::TASK_TABLE, $fields, ['id' => $id]);

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT id, task_name, parent_id, sort_order FROM %s WHERE id = ?', self::TASK_TABLE),
            [$id]
        );

        return $this->json($row ?: []);
    }

    #[Route('/task/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        if (!$this->taskExists($id)) {
            return $this->json(['error' => 'Task not found.'], 404);
        }

        $this->connection->delete(self::TASK_TABLE, ['id' => $id]);

        return $this->json(['deleted' => true]);
    }

    private function seedFromMaster(): void
    {
        $count = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s', self::TASK_TABLE)
        );
        if ($count > 0) {
            return;
        }

        $sql = sprintf(
            "INSERT INTO %s (id, task_name, parent_id, sort_order, created_at, updated_at)
            SELECT
                task_id,
                task_name,
                parent_id,
                MIN(COALESCE(row_num, 0)) AS sort_order,
                NOW(),
                NOW()
            FROM %s
            WHERE task_name IS NOT NULL AND task_name <> ''
            GROUP BY task_id, task_name, parent_id",
            self::TASK_TABLE,
            self::MASTER_TABLE
        );
        $this->connection->executeStatement($sql);
    }

    private function taskExists(int $id): bool
    {
        return (bool) $this->connection->fetchOne(
            sprintf('SELECT 1 FROM %s WHERE id = ?', self::TASK_TABLE),
            [$id]
        );
    }

    private function isDescendant(int $possibleChildId, int $parentId): bool
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, parent_id FROM %s', self::TASK_TABLE)
        );
        $children = [];
        foreach ($rows as $row) {
            $pid = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $children[$pid ?? 0][] = (int) $row['id'];
        }
        $queue = $children[$parentId] ?? [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current === $possibleChildId) {
                return true;
            }
            foreach ($children[$current] ?? [] as $child) {
                $queue[] = $child;
            }
        }
        return false;
    }
}
