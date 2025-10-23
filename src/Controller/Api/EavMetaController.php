<?php
// src/Controller/Api/EavMetaController.php
namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EAV_ADMIN')]
#[Route('/api/eav-meta', name: 'api_eav_meta_')]
class EavMetaController extends AbstractController
{
    public function __construct(private Connection $db) {}

    private function now(): string { return (new \DateTimeImmutable())->format('Y-m-d H:i:s'); }

    // ============================================================
    // TENANTS  (ext_eav.tenants)
    // ============================================================

    #[Route('/tenants', name: 'tenants_list', methods: ['GET'])]
    public function tenantsList(): JsonResponse {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, code, name, created_at
               FROM ext_eav.tenants
              ORDER BY name'
        );
        return $this->json($rows);
    }

    #[Route('/tenants', name: 'tenants_create', methods: ['POST'])]
    public function tenantsCreate(Request $r): JsonResponse {
        $p = $r->toArray();
        $code = trim((string)($p['code'] ?? ''));
        $name = trim((string)($p['name'] ?? ''));

        if (!preg_match('/^[a-z0-9_]{2,64}$/', $code)) {
            return $this->json(['error' => 'Invalid code format'], 422);
        }
        if ($name === '') $name = $code;

        $dup = (int)$this->db->fetchOne('SELECT COUNT(*) FROM ext_eav.tenants WHERE code = ?', [$code]);
        if ($dup) return $this->json(['error' => 'Duplicate code'], 409);

        $this->db->insert('ext_eav.tenants', [
            'code' => $code,
            'name' => $name,
            // created_at has DEFAULT CURRENT_TIMESTAMP
        ]);
        return $this->json(['id' => (int)$this->db->lastInsertId()], 201);
    }

    #[Route('/tenants/{id<\d+>}', name: 'tenants_update', methods: ['PUT','PATCH'])]
    public function tenantsUpdate(int $id, Request $r): JsonResponse {
        $p = $r->toArray();
        $data = array_intersect_key($p, array_flip(['code','name']));
        if (isset($data['code'])) {
            $data['code'] = trim((string)$data['code']);
            if (!preg_match('/^[a-z0-9_]{2,64}$/', $data['code'])) {
                return $this->json(['error' => 'Invalid code format'], 422);
            }
            $dup = (int)$this->db->fetchOne(
                'SELECT COUNT(*) FROM ext_eav.tenants WHERE code = ? AND id <> ?',
                [$data['code'], $id]
            );
            if ($dup) return $this->json(['error' => 'Duplicate code'], 409);
        }
        if ($data) $this->db->update('ext_eav.tenants', $data, ['id' => $id]);
        return $this->json(['ok' => true]);
    }

    #[Route('/tenants/{id<\d+>}', name: 'tenants_delete', methods: ['DELETE'])]
    public function tenantsDelete(int $id): JsonResponse {
        $types = (int)$this->db->fetchOne('SELECT COUNT(*) FROM ext_eav.entity_types WHERE tenant_id = ?', [$id]);
        if ($types > 0) {
            return $this->json(['error' => 'Tenant has dependent types', 'dependent_types' => $types], 409);
        }
        $this->db->delete('ext_eav.tenants', ['id' => $id]);
        return $this->json(['ok' => true]);
    }

    // ============================================================
    // TYPES  (ext_eav.entity_types)
    // ============================================================

    #[Route('/types', name: 'types_list', methods: ['GET'])]
    public function typesList(Request $r): JsonResponse {
        $tenantId = $r->query->getInt('tenant_id', 0);
        if ($tenantId <= 0) return $this->json([], 200);

        $rows = $this->db->fetchAllAssociative(
            'SELECT id, tenant_id, code, name, icon
               FROM ext_eav.entity_types
              WHERE tenant_id = ?
              ORDER BY name',
            [$tenantId]
        );
        return $this->json($rows);
    }

    #[Route('/types', name: 'types_create', methods: ['POST'])]
    public function typesCreate(Request $r): JsonResponse {
        $p = $r->toArray();
        $tenantId = (int)($p['tenant_id'] ?? 0);
        $code = trim((string)($p['code'] ?? ''));
        $name = trim((string)($p['name'] ?? ''));

        if ($tenantId <= 0) return $this->json(['error' => 'tenant_id required'], 422);
        if (!preg_match('/^[a-z0-9_]{2,64}$/', $code)) return $this->json(['error'=>'Invalid code format'],422);
        if ($name === '') $name = $code;

        $dup = (int)$this->db->fetchOne(
            'SELECT COUNT(*) FROM ext_eav.entity_types WHERE tenant_id = ? AND code = ?',
            [$tenantId, $code]
        );
        if ($dup) return $this->json(['error' => 'Duplicate code in tenant'], 409);

        $this->db->insert('ext_eav.entity_types', [
            'tenant_id' => $tenantId,
            'code'      => $code,
            'name'      => $name,
            'icon'      => $p['icon'] ?? null,
        ]);
        return $this->json(['id' => (int)$this->db->lastInsertId()], 201);
    }

    #[Route('/types/{id<\d+>}', name: 'types_update', methods: ['PUT','PATCH'])]
    public function typesUpdate(int $id, Request $r): JsonResponse {
        $p = $r->toArray();
        $data = array_intersect_key($p, array_flip(['tenant_id','code','name','icon']));
        if (isset($data['code'])) {
            if (!preg_match('/^[a-z0-9_]{2,64}$/', (string)$data['code'])) return $this->json(['error'=>'Invalid code format'],422);
            $tenantId = isset($data['tenant_id'])
                ? (int)$data['tenant_id']
                : (int)$this->db->fetchOne('SELECT tenant_id FROM ext_eav.entity_types WHERE id = ?', [$id]);
            $dup = (int)$this->db->fetchOne(
                'SELECT COUNT(*) FROM ext_eav.entity_types WHERE tenant_id=? AND code=? AND id<>?',
                [$tenantId, $data['code'], $id]
            );
            if ($dup) return $this->json(['error'=>'Duplicate code in tenant'],409);
        }
        if ($data) $this->db->update('ext_eav.entity_types', $data, ['id' => $id]);
        return $this->json(['ok' => true]);
    }

    #[Route('/types/{id<\d+>}', name: 'types_delete', methods: ['DELETE'])]
    public function typesDelete(int $id): JsonResponse {
        $maps = (int)$this->db->fetchOne('SELECT COUNT(*) FROM ext_eav.type_attributes WHERE entity_type_id = ?', [$id]);
        $ents = (int)$this->db->fetchOne('SELECT COUNT(*) FROM ext_eav.entities WHERE entity_type_id = ?', [$id]);
        if ($maps || $ents) {
            return $this->json(['error' => 'Type has dependencies', 'mapped_attributes' => $maps, 'entities' => $ents], 409);
        }
        $this->db->delete('ext_eav.entity_types', ['id' => $id]);
        return $this->json(['ok' => true]);
    }

    // ============================================================
    // ATTRIBUTES  (ext_eav.attributes)
    // ============================================================

    #[Route('/attributes', name: 'attributes_list', methods: ['GET'])]
    public function attributesList(Request $r): JsonResponse {
        $tenantId = $r->query->getInt('tenant_id', 0);
        if ($tenantId <= 0) return $this->json([], 200);

        $q = trim((string)$r->query->get('search', ''));
        $sql = 'SELECT id, tenant_id, code, label, data_type, unit, description, is_searchable, is_indexed, rbac_visibility, owner_role_id
                  FROM ext_eav.attributes
                 WHERE tenant_id = ?';
        $args = [$tenantId];
        if ($q !== '') { $sql .= ' AND (code LIKE ? OR label LIKE ?)'; $args[]="%$q%"; $args[]="%$q%"; }
        $sql .= ' ORDER BY code';
        return $this->json($this->db->fetchAllAssociative($sql, $args));
    }

    #[Route('/attributes', name: 'attributes_create', methods: ['POST'])]
    public function attributesCreate(Request $r): JsonResponse {
        $p = $r->toArray();
        $tenantId = (int)($p['tenant_id'] ?? 0);
        $code     = trim((string)($p['code'] ?? ''));
        $label    = trim((string)($p['label'] ?? ''));
        $dtype    = (string)($p['data_type'] ?? 'string');

        if ($tenantId <= 0) return $this->json(['error'=>'tenant_id required'],422);
        if (!preg_match('/^[a-z0-9_.]{2,64}$/', $code)) return $this->json(['error'=>'Invalid code format'],422);
        if ($label === '') $label = $code;

        $allowed = ['string','text','integer','decimal','boolean','datetime','json','reference','ip','cidr'];
        if (!in_array($dtype, $allowed, true)) return $this->json(['error'=>'Invalid data_type'],422);

        $dup = (int)$this->db->fetchOne(
            'SELECT COUNT(*) FROM ext_eav.attributes WHERE tenant_id=? AND code=?',
            [$tenantId, $code]
        );
        if ($dup) return $this->json(['error'=>'Duplicate code in tenant'],409);

        $this->db->insert('ext_eav.attributes', [
            'tenant_id'      => $tenantId,
            'code'           => $code,
            'label'          => $label,
            'data_type'      => $dtype,
            'unit'           => $p['unit'] ?? null,
            'description'    => $p['description'] ?? null,
            'is_searchable'  => (int)($p['is_searchable'] ?? 1),
            'is_indexed'     => (int)($p['is_indexed'] ?? 0),
            'rbac_visibility'=> $p['rbac_visibility'] ?? 'tenant',
            'owner_role_id'  => $p['owner_role_id'] ?? null,
        ]);
        return $this->json(['id' => (int)$this->db->lastInsertId()], 201);
    }

    #[Route('/attributes/{id<\d+>}', name: 'attributes_update', methods: ['PUT','PATCH'])]
    public function attributesUpdate(int $id, Request $r): JsonResponse {
        $p = $r->toArray();
        $data = array_intersect_key($p, array_flip([
            'tenant_id','code','label','data_type','unit','description',
            'is_searchable','is_indexed','rbac_visibility','owner_role_id'
        ]));

        if (isset($data['code'])) {
            if (!preg_match('/^[a-z0-9_.]{2,64}$/', (string)$data['code'])) return $this->json(['error'=>'Invalid code format'],422);
            $tenantId = isset($data['tenant_id'])
                ? (int)$data['tenant_id']
                : (int)$this->db->fetchOne('SELECT tenant_id FROM ext_eav.attributes WHERE id = ?', [$id]);
            $dup = (int)$this->db->fetchOne(
                'SELECT COUNT(*) FROM ext_eav.attributes WHERE tenant_id=? AND code=? AND id<>?',
                [$tenantId, $data['code'], $id]
            );
            if ($dup) return $this->json(['error'=>'Duplicate code in tenant'],409);
        }
        if (isset($data['data_type'])) {
            $allowed = ['string','text','integer','decimal','boolean','datetime','json','reference','ip','cidr'];
            if (!in_array($data['data_type'], $allowed, true)) return $this->json(['error'=>'Invalid data_type'],422);
        }

        if ($data) $this->db->update('ext_eav.attributes', $data, ['id' => $id]);
        return $this->json(['ok' => true]);
    }

    #[Route('/attributes/{id<\d+>}', name: 'attributes_delete', methods: ['DELETE'])]
    public function attributesDelete(int $id): JsonResponse {
        // prevent delete if mapped or used
        $maps = (int)$this->db->fetchOne('SELECT COUNT(*) FROM ext_eav.type_attributes WHERE attribute_id = ?', [$id]);

        // check across all value tables (fast COUNTs)
        $valueTables = [
            'eav_values_string','eav_values_text','eav_values_integer','eav_values_decimal',
            'eav_values_boolean','eav_values_datetime','eav_values_json','eav_values_reference',
            'eav_values_ip','eav_values_cidr'
        ];
        $vals = 0;
        foreach ($valueTables as $vt) {
            $vals += (int)$this->db->fetchOne("SELECT COUNT(*) FROM ext_eav.$vt WHERE attribute_id = ?", [$id]);
            if ($vals > 0) break;
        }

        if ($maps || $vals) {
            return $this->json(['error'=>'Attribute has dependencies','mapped_in_types'=>$maps,'values'=>$vals],409);
        }
        $this->db->delete('ext_eav.attributes', ['id' => $id]);
        return $this->json(['ok' => true]);
    }

    // ============================================================
    // TYPE ↔ ATTRIBUTE mapping  (ext_eav.type_attributes)
    // ============================================================

    #[Route('/type-attributes', name: 'ta_list', methods: ['GET'])]
    public function taList(Request $r): JsonResponse {
        $typeId = $r->query->getInt('entity_type_id', $r->query->getInt('type_id', 0));
        if ($typeId <= 0) return $this->json([], 200);

        $rows = $this->db->fetchAllAssociative(
            'SELECT
                 ta.entity_type_id,
                 ta.attribute_id,
                 ta.tenant_id,
                 ta.required,
                 ta.unique_per_type,
                 ta.cardinality,
                 ta.default_value,
                 ta.display_order,
                 a.code   AS attribute_code,
                 a.label  AS attribute_label,
                 a.data_type
               FROM ext_eav.type_attributes ta
               JOIN ext_eav.attributes a
                 ON a.id = ta.attribute_id AND a.tenant_id = ta.tenant_id
              WHERE ta.entity_type_id = ?
              ORDER BY ta.display_order, a.code',
            [$typeId]
        );
        return $this->json($rows);
    }

    #[Route('/type-attributes', name: 'ta_create', methods: ['POST'])]
    public function taCreate(Request $r): JsonResponse {
        $p = $r->toArray();
        $typeId = (int)($p['entity_type_id'] ?? $p['type_id'] ?? 0);
        $attrId = (int)($p['attribute_id'] ?? 0);
        if ($typeId <= 0 || $attrId <= 0) return $this->json(['error'=>'entity_type_id and attribute_id required'],422);

        // derive tenant from type
        $tenantId = (int)$this->db->fetchOne('SELECT tenant_id FROM ext_eav.entity_types WHERE id = ?', [$typeId]);
        if ($tenantId <= 0) return $this->json(['error'=>'Unknown entity_type'],422);

        // ensure attribute belongs to same tenant
        $attrTenant = (int)$this->db->fetchOne('SELECT tenant_id FROM ext_eav.attributes WHERE id=?', [$attrId]);
        if ($attrTenant !== $tenantId) return $this->json(['error'=>'Attribute belongs to a different tenant'],422);

        $exists = (int)$this->db->fetchOne(
            'SELECT COUNT(*) FROM ext_eav.type_attributes WHERE tenant_id=? AND entity_type_id=? AND attribute_id=?',
            [$tenantId, $typeId, $attrId]
        );
        if ($exists) return $this->json(['error'=>'Already mapped'],409);

        $this->db->insert('ext_eav.type_attributes', [
            'tenant_id'       => $tenantId,
            'entity_type_id'  => $typeId,
            'attribute_id'    => $attrId,
            'required'        => (int)($p['required'] ?? 0),
            'unique_per_type' => (int)($p['unique_per_type'] ?? 0),
            'cardinality'     => $p['cardinality'] ?? 'one',
            'default_value'   => $p['default_value'] ?? null,
            'display_order'   => (int)($p['display_order'] ?? 1000),
        ]);
        return $this->json(['ok'=>true],201);
    }

    #[Route('/type-attributes/{typeId<\d+>}/{attrId<\d+>}', name: 'ta_update', methods: ['PUT','PATCH'])]
    public function taUpdate(int $typeId, int $attrId, Request $r): JsonResponse {
        $tenantId = (int)$this->db->fetchOne('SELECT tenant_id FROM ext_eav.entity_types WHERE id=?', [$typeId]);
        if ($tenantId <= 0) return $this->json(['error'=>'Unknown entity_type'],422);

        $p = $r->toArray();
        $data = array_intersect_key($p, array_flip(['required','unique_per_type','cardinality','default_value','display_order']));
        if ($data) {
            $this->db->update('ext_eav.type_attributes', $data, [
                'tenant_id'      => $tenantId,
                'entity_type_id' => $typeId,
                'attribute_id'   => $attrId
            ]);
        }
        return $this->json(['ok'=>true]);
    }

    #[Route('/type-attributes/{typeId<\d+>}/{attrId<\d+>}', name: 'ta_delete', methods: ['DELETE'])]
    public function taDelete(int $typeId, int $attrId): JsonResponse {
        $tenantId = (int)$this->db->fetchOne('SELECT tenant_id FROM ext_eav.entity_types WHERE id=?', [$typeId]);
        if ($tenantId <= 0) return $this->json(['error'=>'Unknown entity_type'],422);

        $this->db->delete('ext_eav.type_attributes', [
            'tenant_id'      => $tenantId,
            'entity_type_id' => $typeId,
            'attribute_id'   => $attrId
        ]);
        return $this->json(['ok'=>true]);
    }
}
