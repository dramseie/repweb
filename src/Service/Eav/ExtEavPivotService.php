<?php
namespace App\Service\Eav;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ExtEavPivotService
{
    public function __construct(private Connection $db) {}

    /** Always use ext_eav for EAV (NiFi & app-local use their own schemas). */
    private string $schema = 'ext_eav';

    private function q(string $name): string
    {
        return sprintf('`%s`', str_replace('`', '', $name));
    }

    // ------------------------------------------------------------------
    // Meta discovery for dropdowns
    // ------------------------------------------------------------------
    /** List all tenant codes (for dropdown). */
    public function listTenants(): array
    {
        $rows = $this->db->fetchAllAssociative("SELECT code FROM `ext_eav`.tenants ORDER BY code");
        return array_map(static fn(array $r) => $r['code'], $rows);
    }

    /** List all entity type codes for a tenant (for dropdown). */
    public function listEntityTypes(string $tenantCode): array
    {
        $sql = <<<SQL
        SELECT et.code
        FROM `ext_eav`.entity_types et
        JOIN `ext_eav`.tenants t ON t.id = et.tenant_id
        WHERE t.code = :t
        ORDER BY et.code
        SQL;
        $rows = $this->db->fetchAllAssociative($sql, ['t' => $tenantCode]);
        return array_map(static fn(array $r) => $r['code'], $rows);
    }

    // ------------------------------------------------------------------
    // Column metadata
    // ------------------------------------------------------------------
    /** Attributes for tenant+type (table names from dump: type_attributes, attributes). */
    public function listAttributes(string $tenantCode, string $entityTypeCode): array
    {
        $sql = <<<SQL
        SELECT a.code AS attribute_code, a.data_type
        FROM {$this->q($this->schema)}.type_attributes ta
        JOIN {$this->q($this->schema)}.attributes a
          ON a.id = ta.attribute_id AND a.tenant_id = ta.tenant_id
        JOIN {$this->q($this->schema)}.tenants t ON t.id = ta.tenant_id
        JOIN {$this->q($this->schema)}.entity_types et
          ON et.id = ta.entity_type_id AND et.tenant_id = ta.tenant_id
        WHERE t.code = :t AND et.code = :et
        ORDER BY a.code
        SQL;

        return $this->db->fetchAllAssociative($sql, ['t' => $tenantCode, 'et' => $entityTypeCode]);
    }

    // ------------------------------------------------------------------
    // Data fetch (pivoted rows)
    // ------------------------------------------------------------------
    /**
     * Fetch rows using ext_eav context:
     *  1) Try eav_select_view() which EXECUTEs dynamic SQL inside ext_eav (best).
     *  2) Fallback to eav_show_sql(), then force-qualify core tables and run the SQL text.
     */
    public function fetchRows(
        string $tenantCode,
        string $entityTypeCode,
        int $limit = 200,
        int $offset = 0,
        ?string $search = null
    ): array {
        $rows = [];

        // 1) Preferred: execute in ext_eav via stored proc (returns the result set)
        try {
            $stmt = $this->db->prepare("CALL {$this->q($this->schema)}.eav_select_view(:t,:et)");
            $stmt->bindValue('t', $tenantCode);
            $stmt->bindValue('et', $entityTypeCode);

            // IMPORTANT: fully consume and free the cursor after CALL
            $rs   = $stmt->executeQuery();
            $rows = $rs->fetchAllAssociative();
            $rs->free();
        } catch (\Throwable $e) {
            // 2) Fallback: get SQL text, fix schema, execute
            $stmt = $this->db->prepare("CALL {$this->q($this->schema)}.eav_show_sql(:t,:et)");
            $stmt->bindValue('t', $tenantCode);
            $stmt->bindValue('et', $entityTypeCode);
            $rs  = $stmt->executeQuery();
            $row = $rs->fetchAssociative() ?: null;
            $rs->free();

            if ($row && !empty($row['generated_sql'])) { // dump: column name is generated_sql
                $base = $row['generated_sql'];
                // Force-qualify core tables so we never hit repweb.* by accident.
                foreach ([
                    'entities','attributes','type_attributes','tenants',
                    'eav_values_string','eav_values_integer','eav_values_decimal',
                    'eav_values_boolean','eav_values_datetime','eav_values_json',
                    'eav_values_reference','eav_values_ip','eav_values_cidr'
                ] as $tbl) {
                    // replace `table` not already qualified as `schema`.`table`
                    $base = preg_replace('/`' . $tbl . '`(?!\.)/', "`{$this->schema}`.`{$tbl}`", $base);
                }
                $rows = $this->db->fetchAllAssociative($base);
            }
        }

        // Simple search (in PHP) over ci/name/status for now
        if ($search) {
            $q = mb_strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $r) use ($q) {
                $hay = mb_strtolower(($r['ci'] ?? '') . ' ' . ($r['name'] ?? '') . ' ' . ($r['status'] ?? ''));
                return str_contains($hay, $q);
            }));
        }

        $total = count($rows);
        $rows  = array_slice($rows, $offset, $limit);

        $attr = $this->listAttributes($tenantCode, $entityTypeCode);
        $cols = array_map(static fn(array $a) => $a['attribute_code'], $attr);

        return [
            'columns' => array_values(array_unique(array_merge(['ci', 'name', 'status'], $cols))),
            'rows'    => $rows,
            'total'   => $total,
        ];
    }

    // ------------------------------------------------------------------
    // Write changes (via stored procedure)
    // ------------------------------------------------------------------
    /** Group changes by CI and write through your `eav_upsert` procedure. */
    public function applyChanges(
        string $tenantCode,
        string $entityTypeCode,
        array $changes,
        ?string $updatedBy = null
    ): array {
        $byCi = [];
        foreach ($changes as $c) {
            $ci   = $c['ci']        ?? ($c[0] ?? null);
            $attr = $c['attribute'] ?? ($c[1] ?? null);
            $val  = $c['value']     ?? ($c[2] ?? null);
            if ($ci === null || $attr === null) continue;
            $byCi[$ci][] = ['attr' => $attr, 'val' => $val];
        }

        $applied = 0;
        $errors  = [];

        foreach ($byCi as $ci => $list) {
            // Base fields come from ext_eav.entities
            $base = $this->db->fetchAssociative(
                "SELECT e.name, e.status
                   FROM {$this->q($this->schema)}.entities e
                   JOIN {$this->q($this->schema)}.tenants t ON t.id = e.tenant_id
                   JOIN {$this->q($this->schema)}.entity_types et ON et.id = e.entity_type_id
                  WHERE e.ci = :ci AND t.code = :t AND et.code = :et
                  LIMIT 1",
                ['ci' => $ci, 't' => $tenantCode, 'et' => $entityTypeCode]
            ) ?: ['name' => $ci, 'status' => 'active'];

            $name   = $base['name'] ?? $ci;
            $status = $base['status'] ?? 'active';
            $json   = [];

            foreach ($list as $it) {
                $a = (string) $it['attr'];
                $v = $it['val'];
                if ($a === 'name')   { $name   = (string) $v; continue; }
                if ($a === 'status') { $status = (string) $v; continue; }
                if ($a === 'ci')     { continue; }
                $json[$a] = $v;
            }

            try {
                // ext_eav.eav_upsert(tenant_code, entity_type_code, ci, name, status, json, updated_by)
                $this->db->executeStatement(
                    "CALL {$this->q($this->schema)}.eav_upsert(:t,:et,:ci,:name,:status,:json,:by)",
                    [
                        't'      => $tenantCode,
                        'et'     => $entityTypeCode,
                        'ci'     => $ci,
                        'name'   => $name,
                        'status' => $status,
                        'json'   => json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'by'     => $updatedBy ?? 'repweb',
                    ]
                );
                $applied += count($list);
            } catch (\Throwable $e) {
                $errors[] = ['ci' => $ci, 'message' => $e->getMessage()];
            }
        }

        return ['applied' => $applied, 'errors' => $errors];
    }
}
