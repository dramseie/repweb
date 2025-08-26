<?php

namespace App\Service;

use App\Repository\MenuItemRepository;

class MenuBuilder
{
    public function __construct(private MenuItemRepository $repo) {}

    /**
     * Output shape:
     * [
     *   ['id'=>..,'label'=>..,'icon'=>..,'url'=>..,'external'=>..,
     *    'children'=>[
     *        // if mega: [{ group:'Analytics', items:[{...},{...}] }, ...]
     *        // else:   [{...},{...}]
     *    ],
     *    'isMega'=>true|false
     *   ],
     *   ...
     * ]
     */
    public function build(): array
    {
        $items = $this->repo->findActiveOrdered();

        // index basic nodes
        $nodes = [];
        foreach ($items as $i) {
            $nodes[$i->getId()] = [
                'id'       => $i->getId(),
                'label'    => $i->getLabel(),
                'icon'     => $i->getIcon(),
                'url'      => $i->getUrl(),
                'external' => $i->isExternal(),
                'parentId' => $i->getParentId(),
                'group'    => $i->getMegaGroup(), // only used for children of a mega menu
                'desc'     => $i->getDescription(),
                'badge'    => $i->getBadge(),
                'div'      => $i->hasDividerBefore(),
                'children' => [],
                'isMega'   => false,               // will compute for parents
            ];
        }

        // Attach children
        $roots = [];
        foreach ($nodes as $id => &$n) {
            $pid = $n['parentId'];
            if ($pid && isset($nodes[$pid])) {
                $nodes[$pid]['children'][] = &$n;
            } else {
                $roots[] = &$n;
            }
        }
        unset($n);

        // Determine which parents are "mega" by seeing if their children have groups
        foreach ($roots as &$r) {
            $groups = array_filter(array_map(fn($c) => $c['group'], $r['children']));
            $r['isMega'] = count($groups) > 0;

            if ($r['isMega']) {
                // bucket children by group label
                $byGroup = [];
                foreach ($r['children'] as $c) {
                    $g = $c['group'] ?? 'Other';
                    $byGroup[$g][] = $c;
                }
                // convert to [{group:'X', items:[...]}, ...] for frontend convenience
                $grouped = [];
                foreach ($byGroup as $g => $itemsInGroup) {
                    $grouped[] = ['group' => $g, 'items' => $itemsInGroup];
                }
                $r['children'] = $grouped;
            }
        }
        unset($r);

        return $roots;
    }
}
