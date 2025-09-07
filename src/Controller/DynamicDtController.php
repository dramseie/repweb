<?php
// src/Controller/DynamicDtController.php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class DynamicDtController extends AbstractController
{
    public function __construct(
        private Connection $db,
        private Security $security,
        private RequestStack $requestStack
    ) {}


#[Route('/api/report/{repid}/repparam/columnDefs', name: 'report_repparam_columndefs', methods: ['POST'])]
public function saveColumnDef(int $repid, Request $req): JsonResponse
{
    // Load report
    $r = $this->fetchReport($repid);

    // Parse existing repparam
    $repparam = [];
    if (!empty($r['repparam'])) {
        $tmp = json_decode($r['repparam'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            $repparam = $tmp;
        }
    }

    // Normalize existing defs
    $defs = isset($repparam['columnDefs']) && is_array($repparam['columnDefs'])
        ? $repparam['columnDefs'] : [];

    // Incoming payload
    $inRaw = $req->getContent() ?: '{}';
    $in    = json_decode($inRaw, true) ?? [];

    // ---- Case A: bulk replace ----
    if (!empty($in['replace']) && isset($in['columnDefs']) && is_array($in['columnDefs'])) {
        $clean = [];
        foreach ($in['columnDefs'] as $i => $row) {
            $t = $row['targets']         ?? null;
            $b = $row['createdCellBody'] ?? null;
            if (!is_int($t)) {
                return $this->json(['error' => "columnDefs[$i].targets must be an integer"], 400);
            }
            if (!is_string($b) || $b === '') {
                return $this->json(['error' => "columnDefs[$i].createdCellBody must be a non-empty string"], 400);
            }
            if (stripos($b, '<script') !== false) {
                return $this->json(['error' => "columnDefs[$i] invalid content"], 400);
            }
            $clean[] = ['targets' => $t, 'createdCellBody' => $b];
        }

        // de-dup by targets (keep last)
        $byTarget = [];
        foreach ($clean as $row) { $byTarget[$row['targets']] = $row; }
        $repparam['columnDefs'] = array_values($byTarget);

        $this->db->executeStatement(
            "UPDATE report SET repparam = :rp WHERE repid = :id",
            ['rp' => json_encode($repparam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'id' => $repid]
        );

        return $this->json(['ok' => true, 'mode' => 'bulk_replace', 'repparam' => $repparam]);
    }

    // ---- Case B: delete by targets (optional helper) ----
    if (!empty($in['delete'])) {
        $targets = $in['targets'] ?? null;
        if (!is_int($targets)) {
            return $this->json(['error' => 'targets must be an integer column index'], 400);
        }
        $defs = array_values(array_filter($defs, fn($d) => !isset($d['targets']) || $d['targets'] !== $targets));
        $repparam['columnDefs'] = $defs;

        $this->db->executeStatement(
            "UPDATE report SET repparam = :rp WHERE repid = :id",
            ['rp' => json_encode($repparam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'id' => $repid]
        );

        return $this->json(['ok' => true, 'mode' => 'delete', 'repparam' => $repparam]);
    }

    // ---- Case C: single append/replace (existing behavior) ----
    $targets = $in['targets'] ?? null;
    $body    = $in['createdCellBody'] ?? '';

    if (!is_int($targets)) {
        return $this->json(['error' => 'targets must be an integer column index'], 400);
    }
    if (!is_string($body) || $body === '') {
        return $this->json(['error' => 'createdCellBody must be a non-empty string'], 400);
    }
    if (stripos($body, '<script') !== false) {
        return $this->json(['error' => 'invalid content'], 400);
    }

    // Replace any existing rule for same targets
    $defs = array_values(array_filter($defs, fn($d) => !isset($d['targets']) || $d['targets'] !== $targets));
    $defs[] = ['targets' => $targets, 'createdCellBody' => $body];

    $repparam['columnDefs'] = $defs;

    $this->db->executeStatement(
        "UPDATE report SET repparam = :rp WHERE repid = :id",
        ['rp' => json_encode($repparam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'id' => $repid]
    );

    return $this->json(['ok' => true, 'mode' => 'single_upsert', 'repparam' => $repparam]);
}



    #[Route('/report/{repid}', name: 'report_view', methods: ['GET'])]
    public function view(int $repid): Response
    {
        $report = $this->fetchReport($repid);

        // --- BEGIN: repparam plumbing (added) ------------------------------
        $rawRepparam  = $report['repparam'] ?? '';
        $repparamArr  = [];
        if (is_string($rawRepparam) && $rawRepparam !== '') {
            $tmp = json_decode($rawRepparam, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $repparamArr = $tmp;
            }
        }
        $repparamJson = json_encode($repparamArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // --- END: repparam plumbing ---------------------------------------

        return $this->render('dt/db.html.twig', [
            'repid'         => $repid,
            'report'        => $report,
            'reptitle'      => $report['reptitle'] ?? '',
            'repdesc'       => $report['repdesc'] ?? '',
            // expose repparam to Twig / JS
            'repparam'      => $repparamArr,
            'repparam_json' => $repparamJson,
        ]);
    }

    #[Route('/api/dt/{repid}/columns', name: 'dt_db_columns', methods: ['GET'])]
    public function columns(int $repid): JsonResponse
    {
        $r = $this->fetchReport($repid);
        $cols = $this->deriveColumnsWithTypes($r['repsql'], $this->resolveParams($r['repparam']));
        return $this->json(['columns' => array_map(fn($c) => $c['name'], $cols)]);
    }

    #[Route('/api/dt/{repid}', name: 'dt_db_data', methods: ['GET'])]
    public function data(int $repid, Request $req): JsonResponse|Response
    {
        $r        = $this->fetchReport($repid);
        $baseSql  = $r['repsql'];
        $params   = $this->resolveParams($r['repparam']);
        $colsMeta = $this->deriveColumnsWithTypes($baseSql, $params);
        $columns  = array_map(fn($c) => $c['name'], $colsMeta);

        if (!$columns) {
            $draw = (int)$req->query->get('draw', 1);
            return $this->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'meta'            => ['columns' => []],
            ]);
        }

        $draw         = (int)$req->query->get('draw', 1);
        $start        = max(0, (int)$req->query->get('start', 0));
        $length       = min(100, max(10, (int)$req->query->get('length', 10)));
        $globalSearch = trim((string)($req->query->all('search')['value'] ?? ''));

        $dtColsParam  = $req->query->all('columns') ?? [];
        $perColSearch = [];
        foreach ($dtColsParam as $i => $colSpec) {
            $colName = $columns[$i] ?? null;
            if (!$colName) continue;
            $val = trim((string) ($colSpec['search']['value'] ?? ''));
            if ($val !== '') {
                $isString = $colsMeta[$i]['isString'] ?? true;
                if ($isString) {
                    $perColSearch[$colName] = $val;
                }
            }
        }

        $order       = $req->query->all('order')[0] ?? null;
        $orderColIdx = isset($order['column']) ? (int) $order['column'] : 0;
        $orderDir    = (isset($order['dir']) && strtolower($order['dir']) === 'desc') ? 'DESC' : 'ASC';
        $orderBy     = $columns[$orderColIdx] ?? $columns[0];

        $where = [];
        $binds = [];

        // Add initial params (keep colon for PDO)
        foreach ($params as $k => $v) {
            $binds[$k] = $v;
        }

        if ($globalSearch !== '') {
            $likes = [];
            foreach ($colsMeta as $cm) {
                if (!($cm['isString'] ?? true)) continue;
                $c = $cm['name'];
                $likes[] = "CAST(baseq.`$c` AS CHAR) LIKE :q";
            }
            if ($likes) {
                $where[]     = '(' . implode(' OR ', $likes) . ')';
                $binds[':q'] = '%' . $globalSearch . '%';
            }
        }

        $escapeLike = fn(string $v): string => strtr($v, ['%' => '\%', '_' => '\_']);
        $idx        = 0;
        foreach ($perColSearch as $c => $v) {
            $p = ":qc$idx";
            $where[]    = "CAST(baseq.`$c` AS CHAR) LIKE $p ESCAPE '\\\\'";
            $binds[$p]  = '%' . $escapeLike($v) . '%';
            $idx++;
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // DBAL params without colon
        $dbalParams = [];
        foreach ($binds as $k => $v) {
            $dbalParams[ltrim($k, ':')] = $v;
        }

        // Counts
        $sqlTotal     = "SELECT COUNT(*) FROM ( $baseSql ) baseq";
        $recordsTotal = (int)$this->db->fetchOne($sqlTotal, $params);

        $sqlFiltered      = "SELECT COUNT(*) FROM ( $baseSql ) baseq" . $whereSql;
        $recordsFiltered  = (int)$this->db->fetchOne($sqlFiltered, $dbalParams);

        // Data query
        $selectList  = implode(', ', array_map(fn($c) => "`$c`", $columns));
        $sqlDataBase = "SELECT $selectList FROM ( $baseSql ) baseq" . $whereSql;
        $sqlDataBase .= " ORDER BY `$orderBy` $orderDir";

        if (strtolower($req->query->get('format', '')) === 'csv') {
            $filename = ($r['repshort'] ?: ('report_' . $r['repid'])) . '.csv';

            $response = new StreamedResponse(function () use ($sqlDataBase, $binds, $columns) {
                echo "\xEF\xBB\xBF";
                $out = fopen('php://output', 'w');
                fputcsv($out, $columns);

                $pdo  = $this->db->getNativeConnection();
                $stmt = $pdo->prepare($sqlDataBase);
                foreach ($binds as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->execute();

                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $line = [];
                    foreach ($columns as $c) {
                        $line[] = $row[$c] ?? null;
                    }
                    fputcsv($out, $line);
                }
                fclose($out);
            });

            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            return $response;
        }

        // JSON paginated
        $limit  = max(1, (int)$length);
        $offset = max(0, (int)$start);
        $sqlData = $sqlDataBase . " LIMIT :limit OFFSET :offset";

        $pdo  = $this->db->getNativeConnection();
        $stmt = $pdo->prepare($sqlData);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
            'meta'            => ['columns' => $columns, 'title' => $r['reptitle'] ?? null],
        ]);
    }

    private function fetchReport(int $repid): array
    {
        $r = $this->db->fetchAssociative(
            "SELECT repid, reptype, repshort, reptitle, repdesc, repsql, repparam, repowner, repts
             FROM report WHERE repid = :id",
            ['id' => $repid]
        );
        if (!$r) {
            throw $this->createNotFoundException("Report $repid not found");
        }
        $sql = trim((string) $r['repsql']);
        if (stripos($sql, 'select ') !== 0) {
            throw $this->createAccessDeniedException('Report SQL must start with SELECT');
        }
        if (str_contains($sql, ';')) {
            throw $this->createAccessDeniedException('Multiple statements are not allowed');
        }
        return $r;
    }

    private function resolveParams(?string $json): array
    {
        $out = [];
        if (!$json) return $out;

        $map  = json_decode($json, true) ?? [];
        $req  = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();

        foreach ($map as $name => $spec) {
            if (!is_string($spec) || $spec === '') { $out[$name] = $spec; continue; }
            if ($spec[0] !== '@') { $out[$name] = $spec; continue; }

            if ($spec === '@tenant' && $user && method_exists($user, 'getTenantId')) {
                $out[$name] = $user->getTenantId();
            } elseif (str_starts_with($spec, '@session.')) {
                $key = substr($spec, 9);
                $out[$name] = $req?->getSession()?->get($key);
            } elseif (str_starts_with($spec, '@user.')) {
                $prop   = substr($spec, 6);
                $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $prop)));
                $out[$name] = ($user && method_exists($user, $getter)) ? $user->$getter() : null;
            } else {
                $out[$name] = null;
            }
        }
        return $out;
    }

    private function deriveColumnsWithTypes(string $baseSql, array $params): array
    {
        $sql = "SELECT * FROM ( $baseSql ) baseq WHERE 1=0";
        $pdo = $this->db->getNativeConnection();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        $count = $stmt->columnCount();
        $cols  = [];
        $stringNativeTypes = ['VAR_STRING','STRING','BLOB','TINY_BLOB','MEDIUM_BLOB','LONG_BLOB'];

        for ($i = 0; $i < $count; $i++) {
            $meta = $stmt->getColumnMeta($i) ?: [];
            $name = $meta['name'] ?? null;
            if ($name) {
                $native   = strtoupper((string)($meta['native_type'] ?? ''));
                $isString = in_array($native, $stringNativeTypes, true);
                $cols[]   = ['name' => $name, 'isString' => $isString];
            }
        }

        if (!$cols) {
            $stmt->closeCursor();
            $rows = $this->db->fetchAllAssociative("SELECT * FROM ( $baseSql ) baseq LIMIT 1", $params);
            if ($rows) {
                foreach (array_keys($rows[0]) as $k) {
                    $cols[] = ['name' => $k, 'isString' => true];
                }
            }
        }

        return $cols;
    }
}
