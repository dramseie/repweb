<?php
// src/Service/SearchBuilderSql.php
namespace App\Service;

use Doctrine\DBAL\Query\QueryBuilder;
use DateTimeImmutable;

final class SearchBuilderSql
{
    /**
     * Normalize custom conditions in SearchBuilder payload.
     * - Converts our custom 'date_range_picker' into a standard 'between'
     */
    public function normalize(array &$sb): void
    {
        if (empty($sb['criteria']) || !is_array($sb['criteria'])) {
            return;
        }
        foreach ($sb['criteria'] as &$c) {
            if (($c['condition'] ?? '') === 'date_range_picker'
                && isset($c['value']) && is_array($c['value']) && count($c['value']) === 2
            ) {
                $c['condition'] = 'between';
                // leave 'value' as two timestamps 'YYYY-MM-DD HH:mm:ss'
            }
        }
    }

    /**
     * Apply SearchBuilder criteria to a DBAL QueryBuilder.
     *
     * @param QueryBuilder $qb           Doctrine DBAL QueryBuilder (already has FROM/JOINs)
     * @param array        $sb           SearchBuilder payload (after normalize())
     * @param array        $columnMap    UI column key => SQL expression (whitelist!)
     * @param array        $columnTypes  UI column key => 'date'|'datetime'|'text'|'number'
     * @param string       $prefix       Param prefix to avoid name collisions
     */
    public function apply(QueryBuilder $qb, array $sb, array $columnMap, array $columnTypes = [], string $prefix = 'sb'): void
    {
        if (empty($sb['criteria']) || !is_array($sb['criteria'])) {
            return;
        }

        $i = 0;
        foreach ($sb['criteria'] as $crit) {
            $dataKey   = $crit['data']    ?? null; // UI column key
            $cond      = $crit['condition'] ?? null;
            $value     = $crit['value']   ?? null;

            if (!$dataKey || !$cond || !array_key_exists($dataKey, $columnMap)) {
                // Ignore unknown/unsafe columns
                continue;
            }

            $colExpr   = $columnMap[$dataKey];             // e.g., "o.created_at"
            $colType   = ($columnTypes[$dataKey] ?? 'text');
            $nameBase  = "{$prefix}_{$i}";

            switch ($cond) {
                case 'between':
                    if (!is_array($value) || count($value) !== 2) {
                        break;
                    }
                    [$from, $to] = $value;

                    // Normalize formats + end-of-day for pure DATE columns
                    [$fromStr, $toStr] = $this->normalizeDateRange($from, $to, $colType);

                    $qb->andWhere("{$colExpr} BETWEEN :{$nameBase}_from AND :{$nameBase}_to")
                       ->setParameter("{$nameBase}_from", $fromStr)
                       ->setParameter("{$nameBase}_to",   $toStr);
                    break;

                case 'contains':
                    if (!is_scalar($value)) break;
                    $qb->andWhere("{$colExpr} LIKE :{$nameBase}_c")
                       ->setParameter("{$nameBase}_c", '%' . (string)$value . '%');
                    break;

                case 'startsWith':
                    if (!is_scalar($value)) break;
                    $qb->andWhere("{$colExpr} LIKE :{$nameBase}_sw")
                       ->setParameter("{$nameBase}_sw", (string)$value . '%');
                    break;

                case 'equals':
                    if (is_array($value)) break;
                    $qb->andWhere("{$colExpr} = :{$nameBase}_eq")
                       ->setParameter("{$nameBase}_eq", $value);
                    break;

                case 'greaterThan':
                    if (!is_scalar($value)) break;
                    if ($colType === 'date' || $colType === 'datetime') {
                        $v = $this->toDateString((string)$value, $colType);
                        $qb->andWhere("{$colExpr} > :{$nameBase}_gt")
                           ->setParameter("{$nameBase}_gt", $v);
                    } else {
                        $qb->andWhere("{$colExpr} > :{$nameBase}_gt")
                           ->setParameter("{$nameBase}_gt", $value);
                    }
                    break;

                case 'lessThan':
                    if (!is_scalar($value)) break;
                    if ($colType === 'date' || $colType === 'datetime') {
                        $v = $this->toDateString((string)$value, $colType, endOfDay: $colType === 'date');
                        $qb->andWhere("{$colExpr} < :{$nameBase}_lt")
                           ->setParameter("{$nameBase}_lt", $v);
                    } else {
                        $qb->andWhere("{$colExpr} < :{$nameBase}_lt")
                           ->setParameter("{$nameBase}_lt", $value);
                    }
                    break;

                // Add other SB conditions you use here as needed…

                default:
                    // Unknown condition — ignore safely
                    break;
            }

            $i++;
        }
    }

    private function normalizeDateRange(string $from, string $to, string $colType): array
    {
        $fromStr = $this->toDateString($from, $colType);
        $toStr   = $this->toDateString($to,   $colType, endOfDay: ($colType === 'date'));
        return [$fromStr, $toStr];
    }

    /**
     * Convert incoming value to 'Y-m-d H:i:s' for SQL, with end-of-day for pure DATE if requested.
     */
    private function toDateString(string $value, string $colType, bool $endOfDay = false): string
    {
        // Accepts 'YYYY-MM-DD' or 'YYYY-MM-DD HH:mm:ss'
        $dt = new DateTimeImmutable($value);
        if ($colType === 'date') {
            if ($endOfDay) {
                $dt = $dt->setTime(23, 59, 59);
            } else {
                $dt = $dt->setTime(0, 0, 0);
            }
        }
        return $dt->format('Y-m-d H:i:s');
    }
}
