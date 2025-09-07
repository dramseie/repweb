<?php
namespace App\Controller;

use App\Service\SqlComposerService;
use App\Repository\ReportRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/sqlc')]
final class SqlComposerController extends AbstractController
{
    public function __construct(
        private Connection $db,
        private SqlComposerService $svc,
        private ReportRepository $reports,
    ) {}

    #[Route('/schema', methods: ['GET'])]
    public function schema(Request $r): JsonResponse
    {
        $schema = $r->query->get('schema', $this->db->fetchOne('SELECT DATABASE()'));
        $tables = $this->svc->listTablesAndViews($schema);
        return $this->json(['schema' => $schema, 'objects' => $tables]);
    }

    #[Route('/columns', methods: ['GET'])]
    public function columns(Request $r): JsonResponse
    {
        $schema = $r->query->get('schema', $this->db->fetchOne('SELECT DATABASE()'));
        $table  = $r->query->get('table');
        if (!$table) return $this->json(['error' => 'Missing table'], 400);
        return $this->json($this->svc->listColumns($schema, $table));
    }

    #[Route('/preview', methods: ['POST'])]
    public function preview(Request $r): JsonResponse
    {
        $body  = json_decode($r->getContent(), true) ?? [];
        $sql   = (string)($body['sql'] ?? '');
        $limit = (int)($body['limit'] ?? 200);

        try {
            $rows = $this->svc->safePreview($sql, $limit);
            return $this->json(['rows' => $rows]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/save', methods: ['POST'])]
    public function save(Request $r): JsonResponse
    {
        $data = json_decode($r->getContent(), true) ?? [];
        $now  = (new \DateTimeImmutable('now'));

        $repid = $this->reports->insert([
            'reptype'  => $data['reptype']  ?? 'sql',
            'repshort' => $data['repshort'] ?? null,
            'reptitle' => $data['reptitle'] ?? null,
            'repdesc'  => $data['repdesc']  ?? null,
            'repsql'   => $data['repsql']   ?? null,
            'repparam' => $data['repparam'] ?? null,
            'repowner' => $this->getUser()?->getUserIdentifier() ?? 'anonymous',
            'repts'    => $now->format('Y-m-d H:i:s'),
        ]);

        return $this->json(['ok' => true, 'repid' => $repid]);
    }
}
