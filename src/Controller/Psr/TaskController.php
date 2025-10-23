<?php
namespace App\Controller\Psr;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TaskController extends AbstractController
{
    public function create(Connection $db, Request $r): JsonResponse {
        $d = $r->toArray();
        $db->insert('psr_task', [
            'project_id'  => (int)$d['projectId'],
            'parent_id'   => $d['parentId'] ?? null,
            'wbs_code'    => $d['wbsCode'] ?? null,
            'name'        => $d['name'] ?? 'New Task',
            'rag'         => (int)($d['rag'] ?? 1),
            'progress_pct'=> (int)($d['progressPct'] ?? 0),
            'start_date'  => $d['startDate'] ?? null,
            'due_date'    => $d['dueDate'] ?? null,
            'sort_order'  => (int)($d['sortOrder'] ?? 0),
        ]);
        $id = $db->lastInsertId();
        return $this->read($db, $id);
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
        return $this->json($t);
    }
}
