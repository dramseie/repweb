<?php
namespace App\Controller;

use App\Entity\ReportTile;
use App\Entity\UserTile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserTileController extends AbstractController
{
    #[Route('/api/user-tiles', name: 'api_user_tiles_list', methods: ['GET'])]
    public function list(EM $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'Unauthorized'], 401);

        $rows = $em->getRepository(UserTile::class)->createQueryBuilder('ut')
            ->join('ut.tile', 't')->addSelect('t')
            ->andWhere('ut.user = :u')->setParameter('u', $user)
            ->orderBy('ut.pinned', 'DESC')->addOrderBy('ut.position', 'ASC')
            ->getQuery()->getResult();

        $data = array_map(function(UserTile $ut) {
            $t = $ut->getTile();
            return [
                'id' => $ut->getId(),
                'position' => $ut->getPosition(),
                'pinned' => $ut->isPinned(),
                'layout' => $ut->getLayout(),
                'tile' => [
                    'id' => $t->getId(),
                    'title' => $t->getTitle(),
                    'type' => $t->getType(),
                    'thumbnailUrl' => $t->getThumbnailUrl(),
                    'config' => $t->getConfig(),
                ],
            ];
        }, $rows);

        return $this->json($data);
    }

    #[Route('/api/user-tiles', name: 'api_user_tiles_add', methods: ['POST'])]
    public function add(Request $req, EM $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'Unauthorized'], 401);
        $data = json_decode($req->getContent(), true) ?? [];
        $tileId = (int)($data['tileId'] ?? 0);
        if (!$tileId) return $this->json(['error' => 'tileId required'], 400);

        $tile = $em->find(ReportTile::class, $tileId);
        if (!$tile) return $this->json(['error' => 'Tile not found'], 404);

        $existing = $em->getRepository(UserTile::class)->findOneBy(['user' => $user, 'tile' => $tile]);
        if ($existing) return $this->json(['error' => 'Tile already added'], 409);

        $maxPos = (int)($em->createQuery('SELECT COALESCE(MAX(ut.position), -1) FROM App\\Entity\\UserTile ut WHERE ut.user = :u')
            ->setParameter('u', $user)->getSingleScalarResult());

        $ut = new UserTile();
        $ut->setUser($user);
        $ut->setTile($tile);
        $ut->setPosition($maxPos + 1);
        $em->persist($ut);
        $em->flush();

        return $this->json(['id' => $ut->getId()], 201);
    }

    #[Route('/api/user-tiles/{id}', name: 'api_user_tiles_delete', methods: ['DELETE'])]
    public function delete(int $id, EM $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'Unauthorized'], 401);
        $ut = $em->find(UserTile::class, $id);
        if (!$ut || $ut->getUser()->getId() !== $user->getId()) return $this->json(['error' => 'Not found'], 404);
        $em->remove($ut);
        $em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/api/user-tiles/reorder', name: 'api_user_tiles_reorder', methods: ['PATCH'])]
    public function reorder(Request $req, EM $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'Unauthorized'], 401);
        $data = json_decode($req->getContent(), true) ?? [];
        $order = $data['order'] ?? null; // e.g. [5,9,2]
        if (!is_array($order)) return $this->json(['error' => 'order array required'], 400);

        $repo = $em->getRepository(UserTile::class);
        foreach ($order as $pos => $utId) {
            $ut = $repo->find((int)$utId);
            if ($ut && $ut->getUser()->getId() === $user->getId()) {
                $ut->setPosition((int)$pos);
            }
        }
        $em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/api/user-tiles/{id}/layout', name: 'api_user_tiles_layout', methods: ['PATCH'])]
    public function layout(int $id, Request $req, EM $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'Unauthorized'], 401);
        $ut = $em->find(UserTile::class, $id);
        if (!$ut || $ut->getUser()->getId() !== $user->getId()) return $this->json(['error' => 'Not found'], 404);

        $data = json_decode($req->getContent(), true) ?? [];
        $layout = $data['layout'] ?? null;
        if (!is_array($layout)) return $this->json(['error' => 'layout object required'], 400);
        $ut->setLayout($layout);
        $em->flush();
        return $this->json(['ok' => true]);
    }
}
