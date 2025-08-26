<?php

namespace App\Controller\Api;

use App\Entity\Report;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dt', name: 'api_dt_')]
class DataTableController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('/{id}/columns', name: 'columns', methods: ['GET'])]
    public function columns(Request $req, Report $report): JsonResponse|Response
    {
        // Security is handled by security.yaml (ROLE_USER on ^/api/dt/)
        $sql = trim((string) $report->getRepsql());
        if ($sql === '') {
            return $this->json(['columns' => []]);
        }
        return $this->json(['columns' => $this->discoverColumns($sql)]);
    }

    #[Route('/{id}', name: 'data', methods: ['GET'])]
    public function data(Request $req, Report $report): Response
    {
        // Security is handled by security.yaml (ROLE_USER on ^/api/dt/)
        $baseSql = trim((string) $report->getRepsql());
        if ($baseSql === '') {
            return $this->json([
                'draw' => (int) $req->query->get('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        // 1) Column discovery
        $columns = $this->discoverColumns($baseSql);
        if (!$columns) {
            return $this->json([
                'draw' => (int) $req->query->get('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        // 2) DataTables params
        $draw   = (int) $req->query->get('draw', 0);
        $start  = max(0, (int) $req->query->get('start', 0));
        $length = (int) $req->query->get('length', 10);
        if ($length < 1 || $length > 100000) $length = 10;

        // Global search
        $globalSearch = '';
        $searchBag = $req->query->all('search');
        if (is_array($searchBag) && array_key_exists('value', $searchBag)) {
            $globalSearch = (string) $searchBag['value'];
        }

        // Per-column search
        $incomingColumns = $req->query->all('columns') ?? [];
        $colSearch = []; // [index => value]
        foreach ($incomingColumns as $idx => $def) {
            $val = '';
            if (isset($def['search']) && is_array($def['search']) && array_key_exists('value', $def['search'])) {
                $val = (string) $def['search']['value'];
            }
            if ($val !== '' && isset($columns[$idx])) {
                $colSearch[(int)$idx] = $val;
            }
        }

        // Ordering
        $orderBy = '';
        $incomingOrder = $req->query->all('order') ?? [];
        if (!empty($incomingOrder)) {
            $parts = [];
            foreach ($incomingOrder as $o) {
                $idx = isset($o['column']) ? (int) $o['column'] : -1;
                $dir = (isset($o['dir']) && strtolower((string)$o['dir']) === 'desc') ? 'DESC' : 'ASC';
                if (isset($columns[$idx])) {
                    $parts[] = $this->quoteIdent($columns[$idx]) . ' ' . $dir;
                }
            }
            if ($parts) $orderBy = ' ORDER BY ' . implode(', ', $parts);
        }

        // 3) WHERE (PDO-style params with leading :)
        $whereParts = [];
        $pdoParams  = [];

        // Global search → OR across all columns
        if ($globalSearch !== '') {
            $ors = [];
            foreach ($columns as $i => $name) {
                $id = 'baseq.' . $this->quoteIdent($name);
                $k  = ':g' . $i;
                $ors[] = "CAST($id AS CHAR) LIKE $k";
                $pdoParams[$k] = '%' . $globalSearch . '%';
            }
            if ($ors) $whereParts[] = '(' . implode(' OR ', $ors) . ')';
        }

        // Per-column LIKE (escape % and _)
        if (!empty($colSearch)) {
            $esc = fn(string $v): string => strtr($v, ['%' => '\%', '_' => '\_']);
            foreach ($colSearch as $i => $val) {
                $name = $columns[$i];
                $id   = 'baseq.' . $this->quoteIdent($name);
                $k    = ':c' . $i;
                $whereParts[] = "CAST($id AS CHAR) LIKE $k ESCAPE '\\\\'";
                $pdoParams[$k] = '%' . $esc($val) . '%';
            }
        }

        // 🔎 SearchBuilder — accept array, JSON string, or `searchBuilderJson`
        $sb = $req->query->all('searchBuilder') ?: null;
        if (!$sb) {
            $raw = $req->query->get('searchBuilder');
            if (is_string($raw) && $raw !== '') {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) $sb = $tmp;
            }
        }
        if (!$sb) {
            $raw = $req->query->get('searchBuilderJson');
            if (is_string($raw) && $raw !== '') {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) $sb = $tmp;
            }
        }
        if (is_array($sb) && $sb) {
            $sbSql = $this->buildSearchBuilderWhere($sb, $columns, $pdoParams);
            if ($sbSql !== '') $whereParts[] = '(' . $sbSql . ')';
        }

        $whereSql = $whereParts ? (' WHERE ' . implode(' AND ', $whereParts)) : '';

        // DBAL uses keys without colons
        $dbalParams = [];
        foreach ($pdoParams as $k => $v) {
            $dbalParams[ltrim($k, ':')] = $v;
        }

        // 4) Counts
        $countTotalSql    = 'SELECT COUNT(*) AS cnt FROM (' . $baseSql . ') AS baseq';
        $countFilteredSql = 'SELECT COUNT(*) AS cnt FROM (' . $baseSql . ') AS baseq' . $whereSql;

        $total    = (int) ($this->db->fetchOne($countTotalSql) ?? 0);
        $filtered = (int) ($this->db->fetchOne($countFilteredSql, $dbalParams) ?? 0);

        // 5) Page data
        $dataSql = 'SELECT * FROM (' . $baseSql . ') AS baseq' . $whereSql . $orderBy . ' LIMIT :limit OFFSET :offset';

        /** @var \PDO $pdo */
        $pdo = $this->db->getNativeConnection();
        $stmt = $pdo->prepare($dataSql);

        foreach ($pdoParams as $k => $v) {
            $stmt->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $length, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, \PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // CSV for current page (keep behavior)
        if (strtolower((string)$req->query->get('format', '')) === 'csv') {
            $csv = $this->toCsv($columns, $rows);
            return new Response(
                $csv,
                200,
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="report_' . $report->getRepid() . '.csv"',
                    'Cache-Control' => 'no-store',
                ]
            );
        }

        return $this->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    /* ---------------------- SearchBuilder helpers ---------------------- */

    private function buildSearchBuilderWhere(array $sb, array $columns, array &$pdoParams): string
    {
        $logic = strtoupper((string) ($sb['logic'] ?? 'AND'));
        $logic = ($logic === 'OR') ? 'OR' : 'AND';

        $parts = [];

        if (!empty($sb['groups']) && is_array($sb['groups'])) {
            foreach ($sb['groups'] as $group) {
                $g = $this->buildSearchBuilderWhere($group, $columns, $pdoParams);
                if ($g !== '') $parts[] = "($g)";
            }
        }

        $criteria = $sb['criteria'] ?? [];
        if (is_array($criteria)) {
            foreach ($criteria as $crit) {
                $sql = $this->buildSearchBuilderCriterion($crit, $columns, $pdoParams);
                if ($sql !== '') $parts[] = "($sql)";
            }
        }

        return implode(" $logic ", $parts);
    }

    private function buildSearchBuilderCriterion(array $c, array $columns, array &$pdoParams): string
    {
        $idx = isset($c['columnIdx']) ? (int) $c['columnIdx'] : null;

        if ($idx === null) {
            $resolved = $this->resolveSbColumn($c, $columns);
            if ($resolved === null) return '';
            $found = array_search($resolved, $columns, true);
            if ($found === false) return '';
            $idx = (int) $found;
        }
        if (!isset($columns[$idx])) return '';

        $colName = $columns[$idx];
        $id      = 'baseq.' . $this->quoteIdent($colName);

        $type = strtolower((string) ($c['type'] ?? 'string'));
        $cond = strtolower((string) ($c['condition'] ?? 'contains'));

        $vals = [];
        if (array_key_exists('value', $c)) $vals = is_array($c['value']) ? $c['value'] : [$c['value']];
        if (isset($c['value1']) && $c['value1'] !== '') $vals[0] = $c['value1'];
        if (isset($c['value2']) && $c['value2'] !== '') $vals[1] = $c['value2'];
        $vals = array_map(static fn($v) => is_scalar($v) ? (string)$v : '', $vals);

        $base = 'sb_' . substr(md5(json_encode([$idx, $type, $cond, $vals, count($pdoParams)])), 0, 8);
        $p = fn(string $suf) => ':' . $base . '_' . $suf;

        if ($type === 'num' || $type === 'number') {
            $v1 = is_numeric($vals[0] ?? null) ? (string)$vals[0] : null;
            $v2 = is_numeric($vals[1] ?? null) ? (string)$vals[1] : null;
            switch ($cond) {
                case '=':  if ($v1===null) return ''; $pdoParams[$p('v')]=$v1; return "$id = "  . $p('v');
                case '!=': if ($v1===null) return ''; $pdoParams[$p('v')]=$v1; return "$id <> " . $p('v');
                case '>':  if ($v1===null) return ''; $pdoParams[$p('v')]=$v1; return "$id > "  . $p('v');
                case '>=': if ($v1===null) return ''; $pdoParams[$p('v')]=$v1; return "$id >= " . $p('v');
                case '<':  if ($v1===null) return ''; $pdoParams[$p('v')]=$v1; return "$id < "  . $p('v');
                case '<=': if ($v1===null) return ''; $pdoParams[$p('v')]=$v1; return "$id <= " . $p('v');
                case 'between':
                    if ($v1===null) return '';
                    $pdoParams[$p('a')]=$v1; $pdoParams[$p('b')]=$v2 ?? $v1;
                    return "($id BETWEEN " . $p('a') . " AND " . $p('b') . ")";
                default:
                    $pdoParams[$p('v')] = '%' . ($vals[0] ?? '') . '%';
                    return "CAST($id AS CHAR) LIKE " . $p('v');
            }
        }

        if ($type === 'date' || $type === 'datetime') {
            $colDate = "DATE($id)";
            $v1 = $vals[0] ?? '';
            $v2 = $vals[1] ?? '';
            switch ($cond) {
                case '=':  $pdoParams[$p('v')]=$v1; return "$colDate = "  . $p('v');
                case '!=': $pdoParams[$p('v')]=$v1; return "$colDate <> " . $p('v');
                case '>':  $pdoParams[$p('v')]=$v1; return "$colDate > "  . $p('v');
                case '>=': $pdoParams[$p('v')]=$v1; return "$colDate >= " . $p('v');
                case '<':  $pdoParams[$p('v')]=$v1; return "$colDate < "  . $p('v');
                case '<=': $pdoParams[$p('v')]=$v1; return "$colDate <= " . $p('v');
                case 'between':
                    $pdoParams[$p('a')]=$v1; $pdoParams[$p('b')]=$v2 ?: $v1;
                    return "($colDate BETWEEN " . $p('a') . " AND " . $p('b') . ")";
                case 'null':  return "$id IS NULL";
                case '!null': return "$id IS NOT NULL";
                default:
                    $pdoParams[$p('v')] = '%' . ($vals[0] ?? '') . '%';
                    return "CAST($id AS CHAR) LIKE " . $p('v');
            }
        }

        // string (default)
        switch ($cond) {
            case 'contains':  $pdoParams[$p('v')] = '%' . ($vals[0] ?? '') . '%'; return "CAST($id AS CHAR) LIKE " . $p('v');
            case '!contains': $pdoParams[$p('v')] = '%' . ($vals[0] ?? '') . '%'; return "CAST($id AS CHAR) NOT LIKE " . $p('v');
            case 'starts':    $pdoParams[$p('v')] = ($vals[0] ?? '') . '%';       return "CAST($id AS CHAR) LIKE " . $p('v');
            case 'ends':      $pdoParams[$p('v')] = '%' . ($vals[0] ?? '');       return "CAST($id AS CHAR) LIKE " . $p('v');
            case '=':
            case 'equals':    $pdoParams[$p('v')] = ($vals[0] ?? '');             return "$id = " . $p('v');
            case '!=':
            case 'notequals':
            case 'notEquals': $pdoParams[$p('v')] = ($vals[0] ?? '');             return "$id <> " . $p('v');
            case 'null':      return "$id IS NULL";
            case '!null':     return "$id IS NOT NULL";
            default:
                $pdoParams[$p('v')] = '%' . ($vals[0] ?? '') . '%';
                return "CAST($id AS CHAR) LIKE " . $p('v');
        }
    }

    /**
     * Map SearchBuilder column names back to discovered columns.
     */
    private function resolveSbColumn(array $crit, array $validColumns): ?string
    {
        $pick = null;
        if (isset($crit['origData']) && is_string($crit['origData']) && $crit['origData'] !== '') {
            $pick = $crit['origData'];
        } elseif (isset($crit['data']) && is_string($crit['data']) !== '') {
            $pick = $crit['data'];
        } else {
            return null;
        }

        $pickNorm = str_replace(' ', '_', $pick);

        if (in_array($pickNorm, $validColumns, true)) {
            return $pickNorm;
        }

        $map = [];
        foreach ($validColumns as $vc) $map[strtolower($vc)] = $vc;
        $key = strtolower($pickNorm);
        return $map[$key] ?? null;
    }

    /* ---------------------- Utils ---------------------- */

    private function discoverColumns(string $sql): array
    {
        try {
            $wrapped = 'SELECT * FROM (' . $sql . ') AS _t LIMIT 0';
            /** @var \PDO $pdo */
            $pdo = $this->db->getNativeConnection();
            $stmt = $pdo->prepare($wrapped);
            $stmt->execute();

            $cols = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i) ?: [];
                $name = $meta['name'] ?? ('col' . $i);
                $cols[] = $name;
            }
            return $cols;
        } catch (\Throwable $e) {
            try {
                $rows = $this->db->fetchAllAssociative($sql . ' LIMIT 1');
                return $rows ? array_keys($rows[0]) : [];
            } catch (\Throwable $ignored) {
                return [];
            }
        }
    }

    private function quoteIdent(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function toCsv(array $columns, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        // BOM for Excel
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $columns);
        foreach ($rows as $r) {
            $line = [];
            foreach ($columns as $c) $line[] = $r[$c] ?? '';
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        // Normalize newlines to CRLF
        return str_replace("\n", "\r\n", $csv);
    }
}
