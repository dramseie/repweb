<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use RuntimeException;

final class ExtEavRepository
{
    private string $s; // schema name (quoted only once in helper)

    public function __construct(
        private Connection $db,
        #[Autowire(env: 'CMDB_SCHEMA')] ?string $schema = null
    ) {
        $this->s = $schema ? trim($schema, '` ') : 'ext_eav';
    }

    private function t(string $table): string
    {
        // backtick-quote once for safety: `ext_eav`.table
        return sprintf('`%s`.%s', $this->s, $table);
    }

    public function tenantId(string $codeOrId = 'cmdb'): int
    {
        if (ctype_digit($codeOrId)) {
            $exists = $this->db->fetchOne("SELECT 1 FROM ".$this->t('tenants')." WHERE id=?", [(int)$codeOrId]);
            if ($exists) return (int)$codeOrId;
        }
        $id = (int)$this->db->fetchOne("SELECT id FROM ".$this->t('tenants')." WHERE code=?", [$codeOrId]);
        if ($id) return $id;
        throw new RuntimeException("Unknown tenant: {$codeOrId}");
    }

    public function listEntityTypes(int $tenantId): array
    {
        $sql = "SELECT id, code, name AS label, icon FROM ".$this->t('entity_types')." WHERE tenant_id=? ORDER BY name";
        return $this->db->fetchAllAssociative($sql, [$tenantId]);
    }

    public function listRelationTypes(int $tenantId): array
    {
        $sql = "SELECT id, code, label, directed FROM ".$this->t('relation_types')." WHERE tenant_id=? ORDER BY label";
        return $this->db->fetchAllAssociative($sql, [$tenantId]);
    }

    private function typeIdByCode(int $tenantId, string $code): int
    {
        $id = $this->db->fetchOne("SELECT id FROM ".$this->t('entity_types')." WHERE tenant_id=? AND code=?", [$tenantId, $code]);
        if (!$id) throw new RuntimeException("Unknown type: $code");
        return (int)$id;
    }

    private function relTypeIdByCode(int $tenantId, string $code): int
    {
        $id = $this->db->fetchOne("SELECT id FROM ".$this->t('relation_types')." WHERE tenant_id=? AND code=?", [$tenantId, $code]);
        if (!$id) throw new RuntimeException("Unknown relation type: $code");
        return (int)$id;
    }

    public function graph(int $tenantId, array $typeCodes = [], array $cis = []): array
    {
        $where  = ['e.tenant_id = ?'];
        $params = [$tenantId];
        $types  = [ParameterType::INTEGER];

        if ($typeCodes) { $where[] = 'et.code IN (?)'; $params[] = $typeCodes; $types[] = ArrayParameterType::STRING; }
        if ($cis)       { $where[] = 'e.ci IN (?)';    $params[] = $cis;       $types[] = ArrayParameterType::STRING; }

        $sql = "SELECT e.ci, e.name, et.code AS type_code
                FROM ".$this->t('entities')." e
                JOIN ".$this->t('entity_types')." et ON et.id = e.entity_type_id
                WHERE ".implode(' AND ', $where)." ORDER BY e.ci LIMIT 500";
        $rows = $this->db->fetchAllAssociative($sql, $params, $types);

        $nodes = array_map(fn($r) => [
            'id'    => $r['ci'],
            'type'  => $r['type_code'],
            'label' => $r['name'],
        ], $rows);

        $ids = array_column($nodes, 'id');
        if (!$ids) return ['nodes' => [], 'edges' => []];

        $edgesSql = "SELECT r.id AS id, r.src_ci, r.dst_ci, rt.code AS type_code, rt.label AS type_label
                     FROM ".$this->t('relations')." r
                     JOIN ".$this->t('relation_types')." rt ON rt.id = r.relation_type_id
                     WHERE r.tenant_id=? AND (r.src_ci IN (?) OR r.dst_ci IN (?))";
        $edgeRows = $this->db->fetchAllAssociative(
            $edgesSql,
            [$tenantId, $ids, $ids],
            [ParameterType::INTEGER, ArrayParameterType::STRING, ArrayParameterType::STRING]
        );

        $edges = array_map(fn($r) => [
            'id'     => (string)$r['id'],
            'source' => $r['src_ci'],
            'target' => $r['dst_ci'],
            'type'   => $r['type_code'],
            'label'  => $r['type_label'],
        ], $edgeRows);

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    public function createEntity(int $tenantId, string $typeCode, string $ci, string $name): void
    {
        $this->db->insert($this->t('entities'), [
            'ci'             => $ci,
            'tenant_id'      => $tenantId,
            'entity_type_id' => $this->typeIdByCode($tenantId, $typeCode),
            'name'           => $name,
            'status'         => 'active',
            'created_at'     => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateEntityName(int $tenantId, string $ci, string $name): void
    {
        $this->db->update(
            $this->t('entities'),
            ['name' => $name, 'updated_at' => (new \DateTime())->format('Y-m-d H:i:s')],
            ['ci' => $ci, 'tenant_id' => $tenantId]
        );
    }

    public function deleteEntity(int $tenantId, string $ci): void
    {
        $this->db->delete($this->t('entities'), ['tenant_id' => $tenantId, 'ci' => $ci]);
    }

    public function upsertAttributes(int $tenantId, string $ci, array $attrs): void
    {
        if (!$attrs) return;

        $codes = array_keys($attrs);
        $defs = $this->db->fetchAllAssociative(
            'SELECT id, code, data_type FROM '.$this->t('attributes').' WHERE tenant_id=? AND code IN (?)',
            [$tenantId, $codes],
            [ParameterType::INTEGER, ArrayParameterType::STRING]
        );
        $map = [];
        foreach ($defs as $d) $map[$d['code']] = ['id' => (int)$d['id'], 'type' => $d['data_type']];

        foreach ($attrs as $code => $val) {
            if (!isset($map[$code])) continue;
            $this->upsertAttr($tenantId, $ci, $map[$code]['id'], $map[$code]['type'], $val);
        }
    }

    private function upsertAttr(int $tenantId, string $ci, int $attrId, string $dt, mixed $val, int $n=1): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        switch ($dt) {
            case 'string': case 'text': case 'ip': case 'cidr': {
                $tbl = $dt==='text' ? 'eav_values_text'
                     : ($dt==='ip' ? 'eav_values_ip'
                     : ($dt==='cidr' ? 'eav_values_cidr' : 'eav_values_string'));
                $this->db->executeStatement(
                    "INSERT INTO ".$this->t($tbl)." (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                    [$tenantId, $ci, $attrId, $n, (string)$val, $now, 'cmdb-modeler']
                );
                break;
            }
            case 'integer': case 'decimal': {
                $tbl = $dt==='integer' ? 'eav_values_integer' : 'eav_values_decimal';
                $this->db->executeStatement(
                    "INSERT INTO ".$this->t($tbl)." (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                    [$tenantId, $ci, $attrId, $n, $val, $now, 'cmdb-modeler']
                );
                break;
            }
            case 'boolean': {
                $this->db->executeStatement(
                    "INSERT INTO ".$this->t('eav_values_boolean')." (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                    [$tenantId, $ci, $attrId, $n, (int)!!$val, $now, 'cmdb-modeler']
                );
                break;
            }
            case 'datetime': {
                $dtv = is_string($val) ? $val : (new \DateTime($val))->format('Y-m-d H:i:s');
                $this->db->executeStatement(
                    "INSERT INTO ".$this->t('eav_values_datetime')." (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                    [$tenantId, $ci, $attrId, $n, $dtv, $now, 'cmdb-modeler']
                );
                break;
            }
            case 'json': {
                $json = is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
                $this->db->executeStatement(
                    "INSERT INTO ".$this->t('eav_values_json')." (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                    [$tenantId, $ci, $attrId, $n, $json, $now, 'cmdb-modeler']
                );
                break;
            }
            case 'reference': {
                $this->db->executeStatement(
                    "INSERT INTO ".$this->t('eav_values_reference')." (tenant_id, entity_ci, attribute_id, n, target_ci, updated_at, updated_by)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE target_ci=VALUES(target_ci), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                    [$tenantId, $ci, $attrId, $n, (string)$val, $now, 'cmdb-modeler']
                );
                break;
            }
        }
    }

    public function createEdge(int $tenantId, string $typeCode, string $src, string $dst): int
    {
        $this->db->executeStatement(
            "INSERT INTO ".$this->t('relations')." (tenant_id, relation_type_id, src_ci, dst_ci, created_at)
             VALUES (?,?,?,?, NOW())
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
            [$tenantId, $this->relTypeIdByCode($tenantId, $typeCode), $src, $dst]
        );
        return (int)$this->db->lastInsertId();
    }

    public function deleteEdge(int $id): void
    {
        $this->db->delete($this->t('relations'), ['id' => $id]);
    }

    // Get entity_type code for a CI
    public function typeCodeForCi(int $tenantId, string $ci): ?string
    {
        return $this->db->fetchOne(
            "SELECT et.code FROM ".$this->t('entities')." e JOIN ".$this->t('entity_types')." et ON et.id=e.entity_type_id
             WHERE e.tenant_id=? AND e.ci=?",
            [$tenantId, $ci],
            [ParameterType::INTEGER, ParameterType::STRING]
        ) ?: null;
    }

    // List attributes (defs) for a type code
    public function attributeDefsForType(int $tenantId, string $typeCode): array
    {
        $sql = "SELECT a.id, a.code, a.label, a.data_type
                FROM ".$this->t('type_attributes')." ta
                JOIN ".$this->t('entity_types')." et ON et.id=ta.entity_type_id
                JOIN ".$this->t('attributes')." a ON a.id=ta.attribute_id
                WHERE ta.tenant_id=? AND et.code=?
                ORDER BY a.label";
        return $this->db->fetchAllAssociative($sql, [$tenantId, $typeCode], [ParameterType::INTEGER, ParameterType::STRING]);
    }

    // Current values for a CI by attribute_id (single 'n' = 1 slot)
    public function attributeValuesForCi(int $tenantId, string $ci, array $defs): array
    {
        if (!$defs) return [];
        $byType = [];
        foreach ($defs as $d) $byType[$d['data_type']][] = (int)$d['id'];

        $out = [];
        foreach ($byType as $dt => $ids) {
            switch ($dt) {
                case 'string': case 'text': case 'ip': case 'cidr':
                    $tbl = $dt==='text'?'eav_values_text':($dt==='ip'?'eav_values_ip':($dt==='cidr'?'eav_values_cidr':'eav_values_string'));
                    $rows = $this->db->fetchAllAssociative(
                        "SELECT attribute_id, value FROM ".$this->t($tbl)." WHERE tenant_id=? AND entity_ci=? AND attribute_id IN (?) AND n=1",
                        [$tenantId, $ci, $ids],
                        [ParameterType::INTEGER, ParameterType::STRING, ArrayParameterType::INTEGER]
                    );
                    foreach ($rows as $r) $out[(int)$r['attribute_id']] = $r['value'];
                    break;
                case 'integer': case 'decimal':
                    $tbl = $dt==='integer'?'eav_values_integer':'eav_values_decimal';
                    $rows = $this->db->fetchAllAssociative(
                        "SELECT attribute_id, value FROM ".$this->t($tbl)." WHERE tenant_id=? AND entity_ci=? AND attribute_id IN (?) AND n=1",
                        [$tenantId, $ci, $ids],
                        [ParameterType::INTEGER, ParameterType::STRING, ArrayParameterType::INTEGER]
                    );
                    foreach ($rows as $r) $out[(int)$r['attribute_id']] = $r['value'];
                    break;
                case 'boolean':
                    $rows = $this->db->fetchAllAssociative(
                        "SELECT attribute_id, value FROM ".$this->t('eav_values_boolean')." WHERE tenant_id=? AND entity_ci=? AND attribute_id IN (?) AND n=1",
                        [$tenantId, $ci, $ids],
                        [ParameterType::INTEGER, ParameterType::STRING, ArrayParameterType::INTEGER]
                    );
                    foreach ($rows as $r) $out[(int)$r['attribute_id']] = (bool)$r['value'];
                    break;
                case 'datetime':
                    $rows = $this->db->fetchAllAssociative(
                        "SELECT attribute_id, value FROM ".$this->t('eav_values_datetime')." WHERE tenant_id=? AND entity_ci=? AND attribute_id IN (?) AND n=1",
                        [$tenantId, $ci, $ids],
                        [ParameterType::INTEGER, ParameterType::STRING, ArrayParameterType::INTEGER]
                    );
                    foreach ($rows as $r) $out[(int)$r['attribute_id']] = $r['value'];
                    break;
                case 'json':
                    $rows = $this->db->fetchAllAssociative(
                        "SELECT attribute_id, value FROM ".$this->t('eav_values_json')." WHERE tenant_id=? AND entity_ci=? AND attribute_id IN (?) AND n=1",
                        [$tenantId, $ci, $ids],
                        [ParameterType::INTEGER, ParameterType::STRING, ArrayParameterType::INTEGER]
                    );
                    foreach ($rows as $r) $out[(int)$r['attribute_id']] = $r['value'];
                    break;
                case 'reference':
                    $rows = $this->db->fetchAllAssociative(
                        "SELECT attribute_id, target_ci AS value FROM ".$this->t('eav_values_reference')." WHERE tenant_id=? AND entity_ci=? AND attribute_id IN (?) AND n=1",
                        [$tenantId, $ci, $ids],
                        [ParameterType::INTEGER, ParameterType::STRING, ArrayParameterType::INTEGER]
                    );
                    foreach ($rows as $r) $out[(int)$r['attribute_id']] = $r['value'];
                    break;
            }
        }
        return $out;
    }

    // Ego graph centered on CI with breadth up to $depth hops
    public function egoGraph(int $tenantId, string $centerCi, int $depth = 1): array
    {
        $depth = max(0, min(10, $depth)); // safety cap

        // Verify CI exists for tenant; otherwise return empty graph
        $exists = $this->db->fetchOne(
            "SELECT 1 FROM ".$this->t('entities')." WHERE tenant_id=? AND ci=?",
            [$tenantId, $centerCi],
            [ParameterType::INTEGER, ParameterType::STRING]
        );
        if (!$exists) {
            return ['nodes' => [], 'edges' => []];
        }

        $seen     = [$centerCi => true];
        $frontier = [$centerCi];
        $edgesAcc = [];

        for ($i = 0; $i < $depth; $i++) {
            if (empty($frontier)) break;

            $rows = $this->db->fetchAllAssociative(
                "SELECT r.id AS id, r.src_ci, r.dst_ci, rt.code AS type_code, rt.label AS type_label
                   FROM ".$this->t('relations')." r
                   JOIN ".$this->t('relation_types')." rt ON rt.id = r.relation_type_id
                  WHERE r.tenant_id = ?
                    AND (r.src_ci IN (?) OR r.dst_ci IN (?))",
                [$tenantId, $frontier, $frontier],
                [ParameterType::INTEGER, ArrayParameterType::STRING, ArrayParameterType::STRING]
            );

            if (!$rows) { $frontier = []; break; }

            $next = [];
            foreach ($rows as $r) {
                $edgesAcc[] = $r;
                foreach ([$r['src_ci'], $r['dst_ci']] as $ci) {
                    if (!isset($seen[$ci])) { $seen[$ci] = true; $next[] = $ci; }
                }
            }
            $frontier = array_values(array_unique($next));
        }

        $cis = array_keys($seen);
        if (empty($cis)) return ['nodes' => [], 'edges' => []];

        $nodeRows = $this->db->fetchAllAssociative(
            "SELECT e.ci, e.name, et.code AS type_code
               FROM ".$this->t('entities')." e
               JOIN ".$this->t('entity_types')." et ON et.id = e.entity_type_id
              WHERE e.tenant_id = ? AND e.ci IN (?)",
            [$tenantId, $cis],
            [ParameterType::INTEGER, ArrayParameterType::STRING]
        );

        $nodes = array_map(
            fn($r) => ['id' => $r['ci'], 'type' => $r['type_code'], 'label' => $r['name']],
            $nodeRows
        );

        $edges = array_map(
            fn($r) => [
                'id'     => (string)$r['id'],
                'source' => $r['src_ci'],
                'target' => $r['dst_ci'],
                'type'   => $r['type_code'],
                'label'  => $r['type_label'],
            ],
            $edgesAcc
        );

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
