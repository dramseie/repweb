<?php
namespace App\Controller\Psr;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TaskController extends AbstractController
{
    public function create(Connection $db, Request $r): JsonResponse {
        $d = $r->toArray();
        $progress = isset($d['progressPct']) ? $this->clampProgress($d['progressPct']) : 0;
        $db->insert('psr_task', [
            'project_id'  => (int)$d['projectId'],
            'parent_id'   => $d['parentId'] ?? null,
            'wbs_code'    => $d['wbsCode'] ?? null,
            'name'        => $d['name'] ?? 'New Task',
            'rag'         => (int)($d['rag'] ?? 1),
            'progress_pct'=> $progress,
            'start_date'  => $d['startDate'] ?? null,
            'due_date'    => $d['dueDate'] ?? null,
            'sort_order'  => (int)($d['sortOrder'] ?? 0),
        ]);
        $id = $db->lastInsertId();
        return $this->read($db, $id);
    }

    public function tree(Connection $db, string $id): JsonResponse {
        $tasks = $db->fetchAllAssociative(
            "SELECT id, project_id AS projectId, parent_id AS parentId, wbs_code AS wbsCode,
                    name, rag, progress_pct AS progressPct, start_date AS startDate,
                    due_date AS dueDate, sort_order AS sortOrder
               FROM psr_task
              WHERE project_id = :pid
           ORDER BY COALESCE(wbs_code,''), sort_order, id",
            ['pid' => $id]
        );

        $logs = $this->loadProgressLogs($db, array_map(fn($t) => (int)$t['id'], $tasks));
        foreach ($tasks as &$task) {
            $taskId = (int)$task['id'];
            $task['progressLog'] = $logs[$taskId] ?? [];
        }

        return $this->json($tasks);
    }

    public function update(Connection $db, Request $r, string $id): JsonResponse {
        $d = $r->toArray();
        $fields = [];
        foreach ([
            'projectId'=>'project_id','parentId'=>'parent_id','wbsCode'=>'wbs_code','name'=>'name',
            'rag'=>'rag','progressPct'=>'progress_pct','startDate'=>'start_date','dueDate'=>'due_date','sortOrder'=>'sort_order'
        ] as $k=>$col) {
            if (array_key_exists($k,$d)) $fields[$col] = $d[$k];
        }
        if (array_key_exists('progress_pct', $fields)) {
            $fields['progress_pct'] = $this->clampProgress($fields['progress_pct']);
        }
        if ($fields) $db->update('psr_task',$fields,['id'=>$id]);
        return $this->read($db, $id);
    }

    public function delete(Connection $db, string $id): JsonResponse {
        $db->delete('psr_task',['id'=>$id]);
        return $this->json(['ok'=>true]);
    }

    private function read(Connection $db, string $id): JsonResponse {
        $t = $db->fetchAssociative("
          SELECT id, project_id AS projectId, parent_id AS parentId, wbs_code AS wbsCode,
                 name, rag, progress_pct AS progressPct, start_date AS startDate, due_date AS dueDate,
                 sort_order AS sortOrder
          FROM psr_task WHERE id=:id
        ", ['id'=>$id]);
        if (!$t) return $this->json(['error'=>'Not found'],404);
        $logs = $this->loadProgressLogs($db, [(int)$id]);
        $t['progressLog'] = $logs[(int)$id] ?? [];
        return $this->json($t);
    }

    public function appendProgressLog(Connection $db, Request $r, string $id): JsonResponse {
        $task = $db->fetchAssociative("SELECT id, progress_pct FROM psr_task WHERE id = :id", ['id' => $id]);
        if (!$task) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $payload = $r->toArray();
        $note = trim((string)($payload['note'] ?? ''));
        if ($note === '') {
            return $this->json(['error' => 'Note is required'], 400);
        }

        $progress = array_key_exists('progressPct', $payload)
            ? $this->clampProgress($payload['progressPct'])
            : (int)$task['progress_pct'];

        $db->insert('psr_task_progress_log', [
            'task_id' => (int)$id,
            'progress_pct' => $progress,
            'note' => $note,
        ]);
        $logId = (int)$db->lastInsertId();

        if (array_key_exists('progressPct', $payload)) {
            $db->update('psr_task', ['progress_pct' => $progress], ['id' => $id]);
        }

        $logRow = $db->fetchAssociative(
            "SELECT id, task_id, created_at, progress_pct, note
               FROM psr_task_progress_log WHERE id = :id",
            ['id' => $logId],
            [ParameterType::INTEGER]
        );

        if (!$logRow) {
            return $this->json(['error' => 'Unable to load log entry'], 500);
        }

        $entry = [
            'id' => (int)$logRow['id'],
            'taskId' => (int)$logRow['task_id'],
            'createdAt' => (string)$logRow['created_at'],
            'progressPct' => (int)$logRow['progress_pct'],
            'note' => (string)$logRow['note'],
        ];

        return $this->json($entry);
    }

    private function loadProgressLogs(Connection $db, array $taskIds): array {
        if (!$taskIds) {
            return [];
        }
        $rows = $db->fetchAllAssociative(
            "SELECT id, task_id, created_at, progress_pct, note
               FROM psr_task_progress_log
              WHERE task_id IN (?)
           ORDER BY created_at DESC, id DESC",
            [$taskIds],
            [ArrayParameterType::INTEGER]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $taskId = (int)$row['task_id'];
            $grouped[$taskId][] = [
                'id' => (int)$row['id'],
                'taskId' => $taskId,
                'createdAt' => (string)$row['created_at'],
                'progressPct' => (int)$row['progress_pct'],
                'note' => (string)$row['note'],
            ];
        }

        return $grouped;
    }

    private function clampProgress(mixed $value): int {
        $val = (int)$value;
        if ($val < 0) {
            return 0;
        }
        if ($val > 100) {
            return 100;
        }
        return $val;
    }
}
