<?php
namespace App\Controller\Psr;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SnapshotController extends AbstractController
{
    public function take(Request $req, Connection $db): JsonResponse {
        $body = $req->toArray();
        $label = $body['label'] ?? (new \DateTime())->format('Y-m-d_His');
        $note  = $body['note']  ?? null;
        $user  = $this->getUser()?->getUserIdentifier() ?? 'system';
        $db->executeStatement("CALL psr_take_snapshot(:label,:note,:user)", [
            'label'=>$label, 'note'=>$note, 'user'=>$user
        ]);
        return $this->json(['ok'=>true,'label'=>$label]);
    }

    public function versions(Connection $db): JsonResponse {
        $rows = $db->fetchAllAssociative("SELECT id,label,note,created_by AS createdBy,created_at AS createdAt
                                          FROM psr_version ORDER BY created_at DESC");
        return $this->json($rows);
    }

    public function compare(Connection $db, int $versionA, int $versionB): JsonResponse {
        $proj = $db->fetchAllAssociative("
          SELECT a.project_id, a.name,
                 a.rag_overall AS rag_a, b.rag_overall AS rag_b,
                 a.progress_pct AS prog_a, b.progress_pct AS prog_b,
                 a.weather_trend AS wthr_a, b.weather_trend AS wthr_b
          FROM psr_project_snap a
          JOIN psr_project_snap b ON b.project_id=a.project_id AND b.version_id=:vb
          WHERE a.version_id=:va
          ORDER BY a.name
        ", ['va'=>$versionA,'vb'=>$versionB]);

        $tasks = $db->fetchAllAssociative("
          SELECT a.project_id, a.task_id, a.wbs_code, a.name,
                 a.rag AS rag_a, b.rag AS rag_b,
                 a.progress_pct AS prog_a, b.progress_pct AS prog_b
          FROM psr_task_snap a
          JOIN psr_task_snap b ON b.task_id=a.task_id AND b.version_id=:vb
          WHERE a.version_id=:va
          ORDER BY a.project_id, a.wbs_code, a.task_id
        ", ['va'=>$versionA,'vb'=>$versionB]);

        return $this->json(['projects'=>$proj,'tasks'=>$tasks]);
    }
}
