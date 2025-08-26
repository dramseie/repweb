<?php
// src/Service/DataTablesQueryBuilder.php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class DataTablesQueryBuilder
{
    public function __construct(private Connection $db) {}

    /**
     * Build the SQL used for export.
     * Assumptions:
     *  - You store the base SQL in table `report(repid, repsql)` (as you mentioned earlier).
     *  - We'll add ORDER BY from DataTables state and skip LIMIT/OFFSET here.
     *  - Optional global search over visible columns (basic LIKE).
     */
    public function buildQuery(string|int $reportId, array $state, bool $forExport = true): array
    {
        // 1) Load base SQL for this report
        $base = $this->db->fetchOne('SELECT repsql FROM report WHERE repid = ?', [$reportId]);
        if (!$base) {
            // Fallback: simple select from a view/table named by convention (adjust to your project)
            $base = "SELECT * FROM report_$reportId";
        }

        // 2) Determine columns (order/visibility) from client state
        $cols = $this->columnsFromState($state);
        if (empty($cols)) {
            // if client sent nothing, infer from DB after first chunk; for now, select all
            $cols = [];
        }

        // 3) Build WHERE for global search (optional, basic)
        $whereSql = '';
        $params   = [];
        $search = trim((string)($state['search'] ?? ''));
        if ($search !== '' && !empty($cols)) {
            // Only search on visible columns that look like text-ish
            $searchable = array_slice(array_column($cols, 'key'), 0, 12); // safety cap
            $parts = [];
            foreach ($searchable as $i => $col) {
                // naive identifier safety – wrap with backticks (MariaDB). Adjust if using aliases.
                $parts[] = sprintf('CAST(`%s` AS CHAR) LIKE :q', str_replace('`','',$col));
            }
            if ($parts) {
                $whereSql = ' WHERE ' . implode(' OR ', $parts);
                $params['q'] = '%' . $search . '%';
            }
        }

        // 4) ORDER BY from DataTables state
        $orderSql = '';
        $order = $state['order'] ?? [];
        if (is_array($order) && !empty($order) && !empty($cols)) {
            $orderClauses = [];
            foreach ($order as $ord) {
                $idx = (int)($ord[0] ?? $ord['column'] ?? -1);
                $dir = strtolower($ord[1] ?? $ord['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
                $col = $cols[$idx]['key'] ?? null;
                if ($col) {
                    $orderClauses[] = sprintf('`%s` %s', str_replace('`','',$col), $dir);
                }
            }
            if ($orderClauses) {
                $orderSql = ' ORDER BY ' . implode(', ', $orderClauses);
            }
        }

        // Final SQL
        $sql = "SELECT * FROM (\n$base\n) t" . $whereSql . $orderSql;

        return ['sql' => $sql, 'params' => $params, 'cols' => $cols];
    }

    public function count(array $query): int
    {
        $sql = "SELECT COUNT(*) AS c FROM (\n{$query['sql']}\n) x";
        return (int)$this->db->fetchOne($sql, $query['params'] ?? []);
    }

    public function fetchChunk(array $query, int $limit, int $offset): array
    {
        // MariaDB/MySQL style LIMIT
        $sql = $query['sql'] . " LIMIT $limit OFFSET $offset";
        return $this->db->fetchAllAssociative($sql, $query['params'] ?? []);
    }

    /**
     * Build export columns (header + keys) from client state.
     * Server will still enforce an allow-list if you add one later.
     */
    public function getExportColumns(array $state): array
    {
        $cols = $this->columnsFromState($state);
        if (!empty($cols)) return $cols;

        // Fallback: unknown until first fetch. We could inspect the first row to get keys.
        return [];
    }

    private function columnsFromState(array $state): array
    {
        $in = $state['columns'] ?? [];
        if (!is_array($in) || empty($in)) return [];

        // Prefer items with {data, title, visible, position}
        // Normalize to {key, title}
        // Keep order as provided (usually the visible order)
        $norm = [];
        foreach ($in as $c) {
            $key   = $c['data']  ?? $c['key']   ?? null;
            $title = $c['title'] ?? $c['data']  ?? $key;
            $vis   = array_key_exists('visible', $c) ? (bool)$c['visible'] : true;
            if (!$key || !$vis) continue;
            $norm[] = ['key' => (string)$key, 'title' => (string)$title];
        }
        // If client kept original indexes, sort by position if present
        usort($norm, function($a, $b) use ($in) {
            $pa = $this->findPos($in, $a['key']);
            $pb = $this->findPos($in, $b['key']);
            return $pa <=> $pb;
        });

        return $norm;
    }

    private function findPos(array $in, string $key): int
    {
        foreach ($in as $i => $c) {
            $k = $c['data'] ?? $c['key'] ?? null;
            if ($k === $key) {
                return (int)($c['position'] ?? $i);
            }
        }
        return 0;
    }
}
