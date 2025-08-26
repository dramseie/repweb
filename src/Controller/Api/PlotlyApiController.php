<?php
namespace App\Controller\Api;

use App\Entity\Report;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/plotly', name: 'api_plotly_')]
class PlotlyApiController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('/{id}/columns', name: 'columns', methods: ['GET'])]
    public function columns(Report $report): JsonResponse
    {
        $baseSql = rtrim((string)$report->getRepsql(), ";\r\n\t ");
        if ($baseSql === '') return $this->json([]);

        [$names, $types] = $this->discoverColumns($baseSql);

        // return plain array of {data,title,type?}
        $cols = [];
        foreach ($names as $i => $n) {
            $t = $types[$i] ?? null;
            $inferred = null;
            if ($t && preg_match('/date|time|timestamp/i', $t)) $inferred = 'date';
            if (!$inferred && preg_match('/(^|_)(date|created_at|updated_at)$/i', $n)) $inferred = 'date';
            if (!$inferred && $t && preg_match('/int|decimal|float|double|real|numeric/i', $t)) $inferred = 'number';
            $cols[] = ['data' => $n, 'title' => $n, 'type' => $inferred];
        }
        return $this->json($cols);
    }

    #[Route('/{id}/data', name: 'data', methods: ['POST'])]
    public function data(Request $req, Report $report): JsonResponse
    {
        $baseSql = rtrim((string)$report->getRepsql(), ";\r\n\t ");
        if ($baseSql === '') return $this->json(['data' => []]);

        $payload = json_decode($req->getContent() ?: '{}', true) ?: [];

        // time range (from <input type="datetime-local">)
        $fromStr = trim((string)($payload['from'] ?? ''));
        $toStr   = trim((string)($payload['to'] ?? ''));

        // choose time column: from repparam JSON or infer from columns
        $timeCol = null;
        if ($report->getRepparam()) {
            try {
                $rp = json_decode($report->getRepparam(), true, 512, JSON_THROW_ON_ERROR);
                if (!empty($rp['time_col']) && preg_match('/^[A-Za-z0-9_]+$/', $rp['time_col'])) {
                    $timeCol = $rp['time_col'];
                }
            } catch (\Throwable $e) {}
        }
        if (!$timeCol) {
            [$names, $types] = $this->discoverColumns($baseSql);
            foreach ($names as $i => $n) {
                $t = strtolower((string)($types[$i] ?? ''));
                if (preg_match('/date|time|timestamp/', $t) || preg_match('/(^|_)(date|created_at|updated_at)$/i', $n)) {
                    $timeCol = $n; break;
                }
            }
        }

        $where = '';
        $params = [];
        if ($timeCol && ($fromStr !== '' || $toStr !== '')) {
            $parts = [];
            if ($fromStr !== '') {
                $from = date_create($fromStr);
                if ($from) { $parts[] = "base.`$timeCol` >= :from"; $params['from'] = $from->format('Y-m-d H:i:s'); }
            }
            if ($toStr !== '') {
                $to = date_create($toStr);
                if ($to) { $parts[] = "base.`$timeCol` <= :to"; $params['to'] = $to->format('Y-m-d H:i:s'); }
            }
            if ($parts) $where = ' WHERE ' . implode(' AND ', $parts);
        }

        $sql = "SELECT * FROM ( $baseSql ) AS base" . $where;

        $rows = $this->db->fetchAllAssociative($sql, $params);
        return $this->json(['data' => $rows]);
    }

    /**
     * @return array{0: string[], 1: string[]}
     */
    private function discoverColumns(string $sql): array
    {
        $pdo = $this->db->getNativeConnection();
        $stmt = $pdo->prepare("SELECT * FROM ( $sql ) AS _t WHERE 1=0");
        $stmt->execute();

        $names = []; $types = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta  = $stmt->getColumnMeta($i) ?: [];
            $names[] = $meta['name'] ?? ('col_'.$i);
            $types[] = strtolower((string)($meta['native_type'] ?? ''));
        }
        return [$names, $types];
    }
}
