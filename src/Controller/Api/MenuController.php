<?php

namespace App\Controller\Api;

use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/api/menu', name: 'api_menu', methods: ['GET'])]
    public function menu(EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->getRepository(MenuItem::class)->createQueryBuilder('m');

        // Pull scalars + parent id for stable tree building
        $rows = $qb
            ->select([
                'm.id            AS id',
                'm.label         AS label',
                'm.url           AS url',
                'm.route         AS route',
                'm.position      AS position',
                'm.dividerBefore AS divider_before',
                'm.megaGroup     AS mega_group',
                'm.description   AS description',
                'm.isActive      AS is_active',
                'm.icon          AS icon',
                'm.badge         AS badge',
                'm.external      AS external',
                'IDENTITY(m.parent) AS parent_id',
                'm.routeParamsText  AS route_params_text',
                'm.rolesText        AS roles_text',
            ])
            ->andWhere('m.isActive = 1')
            ->orderBy('parent_id', 'ASC')   // roots first
            ->addOrderBy('m.position', 'ASC')
            ->addOrderBy('m.label', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Normalize (snake_case + decode JSON)
        $items = array_map(function (array $r): array {
            $routeParams = [];
            if (!empty($r['route_params_text'])) {
                $decoded = json_decode((string)$r['route_params_text'], true);
                if (is_array($decoded)) $routeParams = $decoded;
            }

            $roles = [];
            if (!empty($r['roles_text'])) {
                $decoded = json_decode((string)$r['roles_text'], true);
                if (is_array($decoded)) $roles = array_values($decoded);
            }

            return [
                'id'             => (int) $r['id'],
                'label'          => (string) $r['label'],
                'url'            => $r['url'] ?? null,
                'route'          => $r['route'] ?? null,
                'route_params'   => $routeParams,
                'parent_id'      => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
                'position'       => (int) $r['position'],
                'divider_before' => (bool) $r['divider_before'],
                'mega_group'     => $r['mega_group'] ?? null,
                'description'    => $r['description'] ?? null,
                'is_active'      => (bool) $r['is_active'],
                'roles'          => $roles,
                'icon'           => $r['icon'] ?? null,
                'badge'          => $r['badge'] ?? null,
                'external'       => (bool) $r['external'],
            ];
        }, $rows);

        // Group by parent_id
        $byParent = [];
        foreach ($items as $it) {
            $pid = $it['parent_id'] ?? 0; // 0 bucket for roots
            $byParent[$pid][] = $it;
        }

        // Build tree recursively
        $build = function (int $parentId) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $node) {
                $node['children'] = $build($node['id']);
                $out[] = $node;
            }
            return $out;
        };

        $tree = $build(0);

        // 🔧 Post-process: ensure parent nodes have a clickable URL so the navbar renders them.
        $ensureClickableParents = function (&$nodes) use (&$ensureClickableParents) {
            foreach ($nodes as &$n) {
                if (!empty($n['children'])) {
                    if (($n['url'] ?? null) === null && ($n['route'] ?? null) === null) {
                        $n['url'] = '#';
                    }
                    $ensureClickableParents($n['children']);
                }
            }
        };
        $ensureClickableParents($tree);

        return $this->json($tree);
    }
}
