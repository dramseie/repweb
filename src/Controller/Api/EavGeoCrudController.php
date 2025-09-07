<?php
namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/eav/geo', name: 'api_eav_geo_crud_')]
class EavGeoCrudController extends AbstractController
{
    public function __construct(private Connection $db) {}

    /** Create a new CI in the GeoLocation tenant/type */
    #[Route('/entity', name: 'create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $payload = json_decode($req->getContent(), true) ?: [];
        // Defaults – adjust if your tenant/type differ
        $tenantId = (int)($payload['tenant_id'] ?? 2);
        $etypeId  = (int)($payload['entity_type_id'] ?? 12);

        $ci   = trim((string)($payload['ci'] ?? ''));
        $name = trim((string)($payload['name'] ?? $ci));
        if ($ci === '') return $this->json(['error'=>'ci required'], 400);

        $status  = (string)($payload['status'] ?? 'active');
        $address = (string)($payload['address'] ?? '');
        $city    = (string)($payload['city'] ?? '');
        $country = (string)($payload['country'] ?? '');
        $desc    = (string)($payload['desc'] ?? '');
        $icon    = (string)($payload['icon'] ?? 'fa-solid fa-location-dot');
        $lat     = (string)($payload['lat'] ?? '');
        $long    = (string)($payload['long'] ?? ($payload['lng'] ?? ''));

        $this->db->beginTransaction();
        try {
            // entities insert
            $this->db->executeStatement(
                "INSERT INTO entities (ci, tenant_id, entity_type_id, name, status, created_at, created_by)
                 VALUES (:ci, :tenant_id, :etype_id, :name, :status, NOW(), 'ui')",
                compact('ci','tenantId','etypeId','name','status')
            );

            // attribute id lookup by code (for this type)
            $attrIds = $this->db->fetchAllKeyValue("
              SELECT a.code, a.id
              FROM type_attributes ta
              JOIN attributes a ON a.id = ta.attribute_id
              WHERE ta.entity_type_id = :etype AND ta.tenant_id = :tenant
                AND a.code IN ('address','city','country','desc','icon','lat','long')
            ", ['etype' => $etypeId, 'tenant' => $tenantId]);

            $ins = fn($code,$val) => $val!=='' && isset($attrIds[$code])
                ? $this->db->executeStatement(
                    "INSERT INTO eav_values_string (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                     VALUES (:t,:ci,:aid,1,:val,NOW(),'ui')
                     ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                    ['t'=>$tenantId,'ci'=>$ci,'aid'=>$attrIds[$code],'val'=>$val]
                  )
                : null;

            $ins('address',$address);
            $ins('city',$city);
            $ins('country',$country);
            $ins('desc',$desc);
            $ins('icon',$icon);
            $ins('lat',$lat);
            $ins('long',$long);

            $this->db->commit();
            return $this->json(['ok'=>true,'ci'=>$ci]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return $this->json(['error'=>true,'message'=>$e->getMessage()], 500);
        }
    }

    /** Update existing CI values */
    #[Route('/entity/{ci}', name: 'update', methods: ['PUT'])]
    public function update(string $ci, Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $payload = json_decode($req->getContent(), true) ?: [];

        // Find tenant/type for this CI
        $row = $this->db->fetchAssociative("SELECT tenant_id, entity_type_id FROM entities WHERE ci = :ci", ['ci'=>$ci]);
        if (!$row) return $this->json(['error'=>'CI not found'], 404);
        $tenantId = (int)$row['tenant_id']; $etypeId = (int)$row['entity_type_id'];

        $fields = [
            'name'    => $payload['name'] ?? null,
            'status'  => $payload['status'] ?? null,
        ];
        $vals = [
            'address' => $payload['address'] ?? null,
            'city'    => $payload['city'] ?? null,
            'country' => $payload['country'] ?? null,
            'desc'    => $payload['desc'] ?? null,
            'icon'    => $payload['icon'] ?? null,
            'lat'     => $payload['lat']  ?? null,
            'long'    => $payload['long'] ?? ($payload['lng'] ?? null),
        ];

        $this->db->beginTransaction();
        try {
            // Update entity fields
            $set = [];
            $p = ['ci'=>$ci];
            foreach ($fields as $k=>$v) if ($v !== null) { $set[]="$k=:$k"; $p[$k]=$v; }
            if ($set) {
                $this->db->executeStatement("UPDATE entities SET ".implode(',', $set).", updated_at=NOW(), updated_by='ui' WHERE ci=:ci", $p);
            }

            // Update attribute values
            if (array_filter($vals, fn($v)=>$v!==null)) {
                $attrIds = $this->db->fetchAllKeyValue("
                  SELECT a.code, a.id
                  FROM type_attributes ta
                  JOIN attributes a ON a.id = ta.attribute_id
                  WHERE ta.entity_type_id = :etype AND ta.tenant_id = :tenant
                    AND a.code IN ('address','city','country','desc','icon','lat','long')
                ", ['etype' => $etypeId, 'tenant' => $tenantId]);

                foreach ($vals as $code=>$val) {
                    if ($val === null || !isset($attrIds[$code])) continue;
                    $this->db->executeStatement(
                        "INSERT INTO eav_values_string (tenant_id, entity_ci, attribute_id, n, value, updated_at, updated_by)
                         VALUES (:t,:ci,:aid,1,:val,NOW(),'ui')
                         ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
                        ['t'=>$tenantId,'ci'=>$ci,'aid'=>$attrIds[$code],'val'=>$val]
                    );
                }
            }

            $this->db->commit();
            return $this->json(['ok'=>true,'ci'=>$ci]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return $this->json(['error'=>true,'message'=>$e->getMessage()], 500);
        }
    }
}
