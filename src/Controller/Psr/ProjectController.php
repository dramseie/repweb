<?php
namespace App\Controller\Psr;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ProjectController extends AbstractController
{
    public function index(Connection $db): JsonResponse {
        $rows = $db->fetchAllAssociative("
          SELECT id, name, description, weather_trend AS weatherTrend,
                 rag_overall AS ragOverall, progress_pct AS progressPct,
                 updated_at AS updatedAt
          FROM psr_project ORDER BY updated_at DESC
        ");
        return $this->json($rows);
    }

    public function show(Connection $db, string $id): JsonResponse {
        $p = $db->fetchAssociative("
          SELECT id, name, description, weather_trend AS weatherTrend,
                 rag_overall AS ragOverall, progress_pct AS progressPct,
                 updated_at AS updatedAt
          FROM psr_project WHERE id = :id
        ", ['id'=>$id]);
        if (!$p) return $this->json(['error'=>'Not found'], 404);

        $tasks = $db->fetchAllAssociative("
          SELECT id, project_id AS projectId, parent_id AS parentId, wbs_code AS wbsCode,
                 name, rag, progress_pct AS progressPct, start_date AS startDate,
                 due_date AS dueDate, sort_order AS sortOrder
          FROM psr_task WHERE project_id=:pid
          ORDER BY COALESCE(wbs_code,''), sort_order, id
        ", ['pid'=>$id]);

        $logsByTask = [];
        if ($tasks) {
            $taskIds = array_map(fn($t) => (int)$t['id'], $tasks);
            $logRows = $db->fetchAllAssociative(
                "SELECT id, task_id, created_at, progress_pct, note
                   FROM psr_task_progress_log
                  WHERE task_id IN (?)
               ORDER BY created_at DESC, id DESC",
                [$taskIds],
                [ArrayParameterType::INTEGER]
            );
            foreach ($logRows as $log) {
                $taskId = (int)$log['task_id'];
                $logsByTask[$taskId][] = [
                    'id' => (int)$log['id'],
                    'taskId' => $taskId,
                    'createdAt' => (string)$log['created_at'],
                    'progressPct' => (int)$log['progress_pct'],
                    'note' => (string)$log['note'],
                ];
            }
        }

        // build tree
        $byId = [];
        foreach ($tasks as &$t) {
            $tid = (int)$t['id'];
            $t['children'] = [];
            $t['progressLog'] = $logsByTask[$tid] ?? [];
            $byId[$t['id']] = &$t;
        }
        $root = [];
        foreach ($tasks as &$t) {
            if ($t['parentId']) {
                if (isset($byId[$t['parentId']])) $byId[$t['parentId']]['children'][] = &$t;
                else $root[] = &$t; // orphan fallback
            } else $root[] = &$t;
        }
        $p['tasks'] = $root;
        return $this->json($p);
    }

    public function create(Connection $db, Request $r): JsonResponse {
        $d = $r->toArray();
        $db->insert('psr_project', [
            'name' => $d['name'] ?? 'New Project',
            'description' => $d['description'] ?? null,
            'weather_trend' => (int)($d['weatherTrend'] ?? 3),
            'rag_overall' => (int)($d['ragOverall'] ?? 1),
            'progress_pct' => (int)($d['progressPct'] ?? 0),
        ]);
        $id = $db->lastInsertId();
        return $this->show($db, $id);
    }

    public function update(Connection $db, Request $r, string $id): JsonResponse {
        $d = $r->toArray();
        $fields = [];
        foreach ([
            'name'=>'name','description'=>'description',
            'weatherTrend'=>'weather_trend','ragOverall'=>'rag_overall','progressPct'=>'progress_pct'
        ] as $k=>$col) {
            if (array_key_exists($k,$d)) $fields[$col] = $d[$k];
        }
        if ($fields) $db->update('psr_project',$fields,['id'=>$id]);
        return $this->show($db, $id);
    }

    public function delete(Connection $db, string $id): JsonResponse {
        $db->delete('psr_project',['id'=>$id]);
        return $this->json(['ok'=>true]);
    }
}
