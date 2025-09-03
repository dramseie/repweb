<?php
namespace App\Controller\Api;

use App\Service\{ExtEavRepository, CanvasLayoutStore};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/cmdb/modeler')]
class CmdbModelerApiController extends AbstractController
{
    public function __construct(
        private ExtEavRepository $repo,
        private CanvasLayoutStore $layouts
    ) {}

    /** Common context: resolves tenant id + user ref */
    private function ctx(Request $r): array
    {
        $tenantCode = $r->query->get('tenant', 'cmdb');
        $tenantId   = $this->repo->tenantId($tenantCode);
        $userRef    = $this->getUser()?->getUserIdentifier() ?? 'anon';
        return [$tenantId, $tenantCode, $userRef];
    }

    /* =========================
     * Lookups
     * ========================= */

    #[Route('/types', methods: ['GET'])]
    public function types(Request $r): JsonResponse
    {
        try {
            [$t] = $this->ctx($r);
            return $this->json($this->repo->listEntityTypes($t));
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    #[Route('/relations/types', methods: ['GET'])]
    public function relationTypes(Request $r): JsonResponse
    {
        try {
            [$t] = $this->ctx($r);
            return $this->json($this->repo->listRelationTypes($t));
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    /* =========================
     * Graph
     * ========================= */

    #[Route('/graph', methods: ['GET'])]
    public function graph(Request $r): JsonResponse
    {
        try {
            [$tenant,, $user] = $this->ctx($r);
            $types = array_filter(explode(',', (string)$r->query->get('types', '')));
            $cis   = array_filter(explode(',', (string)$r->query->get('cis', '')));
            $canvasName = (string)$r->query->get('canvas', 'default');

            $g = $this->repo->graph($tenant, $types, $cis);

            // merge saved positions
            $pos = $this->layouts->load($tenant, $user, $canvasName);
            foreach ($g['nodes'] as &$n) {
                if (isset($pos[$n['id']])) {
                    $n['position'] = $pos[$n['id']];
                }
            }
            return $this->json($g);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    // Ego graph: N-hop neighborhood of a CI
    #[Route('/graph/ego', methods: ['GET'])]
    public function graphEgo(Request $r): JsonResponse
    {
        try {
            [$tenantId] = $this->ctx($r);
            $ci    = (string)$r->query->get('ci', '');
            $depth = max(0, (int)$r->query->get('depth', 1));
            if (!$ci) return $this->json(['error' => 'Missing ci'], 400);

            $g = $this->repo->egoGraph($tenantId, $ci, $depth);
            return $this->json($g);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    /* =========================
     * Nodes (CIs)
     * ========================= */

    #[Route('/node', methods: ['POST'])]
    public function createNode(Request $r): JsonResponse
    {
        try {
            [$tenantId] = $this->ctx($r);
            $d = json_decode($r->getContent(), true) ?? [];

            $type = (string)($d['type'] ?? '');
            if (!$type) return $this->json(['error'=>'Missing type'], 400);

            $ci   = (string)($d['ci']   ?? '');
            if (!$ci) {
                // generate a readable CI id when not provided
                $ci = strtoupper(substr($type, 0, 3)) . '-' . substr(sha1(uniqid('', true)), 0, 6);
            }
            $name = (string)($d['name'] ?? $ci);

            $this->repo->createEntity($tenantId, $type, $ci, $name);
            if (!empty($d['attrs']) && is_array($d['attrs'])) {
                $this->repo->upsertAttributes($tenantId, $ci, $d['attrs']);
            }

            return $this->json(['ci'=>$ci, 'label'=>$name, 'type'=>$type]);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    #[Route('/node/{ci}', methods: ['PATCH'])]
    public function updateNode(string $ci, Request $r): JsonResponse
    {
        try {
            [$tenantId] = $this->ctx($r);
            $d = json_decode($r->getContent(), true) ?? [];

            if (array_key_exists('name', $d)) {
                $this->repo->updateEntityName($tenantId, $ci, (string)$d['name']);
            }
            if (!empty($d['attrs']) && is_array($d['attrs'])) {
                $this->repo->upsertAttributes($tenantId, $ci, $d['attrs']);
            }
            return $this->json(['ok'=>true]);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    #[Route('/node/{ci}', methods: ['DELETE'])]
    public function deleteNode(string $ci, Request $r): JsonResponse
    {
        try {
            [$tenantId] = $this->ctx($r);
            $this->repo->deleteEntity($tenantId, $ci);
            return $this->json(['ok'=>true]);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    // Attribute defs + values for a CI (Inspector)
    #[Route('/node/{ci}/attributes', methods: ['GET'])]
    public function nodeAttributes(string $ci, Request $r): JsonResponse
    {
        try {
            [$tenantId] = $this->ctx($r);
            $type = $this->repo->typeCodeForCi($tenantId, $ci);
            if (!$type) return $this->json(['error' => 'CI not found'], 404);

            $defs = $this->repo->attributeDefsForType($tenantId, $type);
            $vals = $this->repo->attributeValuesForCi($tenantId, $ci, $defs);

            $attrs = array_map(function ($d) use ($vals) {
                return [
                    'id'       => (int)$d['id'],
                    'code'     => $d['code'],
                    'label'    => $d['label'],
                    'dataType' => $d['data_type'],
                    'value'    => $vals[(int)$d['id']] ?? null,
                ];
            }, $defs);

            return $this->json(['typeCode' => $type, 'ci' => $ci, 'attrs' => $attrs]);
        } catch (\Throwable $e) { return $this->json(['error' => $e->getMessage()], 500); }
    }

    /* =========================
     * Edges (Relations)
     * ========================= */

    #[Route('/edge', methods: ['POST'])]
    public function createEdge(Request $r): JsonResponse
    {
        try {
            [$tenantId] = $this->ctx($r);
            $d = json_decode($r->getContent(), true) ?? [];

            $type   = (string)($d['type']   ?? '');
            $source = (string)($d['source'] ?? '');
            $target = (string)($d['target'] ?? '');
            if (!$type || !$source || !$target) {
                return $this->json(['error'=>'Missing type/source/target'], 400);
            }

            $id = $this->repo->createEdge($tenantId, $type, $source, $target);
            return $this->json(['id'=>$id]);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    #[Route('/edge/{id}', methods: ['DELETE'])]
    public function deleteEdge(int $id): JsonResponse
    {
        try {
            $this->repo->deleteEdge($id);
            return $this->json(['ok'=>true]);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }

    /* =========================
     * Layout persistence
     * ========================= */

    #[Route('/layout/save', methods: ['POST'])]
    public function saveLayout(Request $r): JsonResponse
    {
        try {
            [$tenant,, $user] = $this->ctx($r);
            $d = json_decode($r->getContent(), true) ?? [];

            $name  = (string)($d['name']  ?? 'default');
            $nodes = (array)($d['nodes']  ?? []);

            $this->layouts->save($tenant, $user, $name, $nodes);
            return $this->json(['ok'=>true]);
        } catch (\Throwable $e) { return $this->json(['error'=>$e->getMessage()], 500); }
    }
}
