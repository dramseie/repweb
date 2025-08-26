<?php
namespace App\Controller;

use App\Entity\ReportTile;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ReportTileController extends AbstractController
{
    #[Route('/api/tiles', name: 'api_tiles_list', methods: ['GET'])]
    public function list(Request $req, EM $em): JsonResponse
    {
        $onlyActive = $req->query->getBoolean('onlyActive', true);
        $repo = $em->getRepository(ReportTile::class);
        $qb = $repo->createQueryBuilder('t');
        if ($onlyActive) $qb->andWhere('t.isActive = 1');
        $tiles = $qb->orderBy('t.title', 'ASC')->getQuery()->getResult();

        $roles = $this->getUser() ? $this->getUser()->getRoles() : [];

        $data = array_values(array_filter(array_map(function(ReportTile $t) use ($roles) {
            $allowed = $t->getAllowedRoles() ?: [];
            if (!empty($allowed) && empty(array_intersect($allowed, $roles))) return null;
            return [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'type' => $t->getType(),
                'thumbnailUrl' => $t->getThumbnailUrl(),
                'config' => $t->getConfig(),
            ];
        }, $tiles)));

        return $this->json($data);
    }
}
