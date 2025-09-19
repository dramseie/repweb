<?php
namespace App\Controller;

use App\Entity\DtSavedFilter;
use App\Repository\DtSavedFilterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Routes:
 *  GET    /api/dt-filters?table_key=...    list (public + mine)
 *  POST   /api/dt-filters                  create {table_key, name, is_public, details_json, state_json?}
 *  DELETE /api/dt-filters/{id}             delete (owner or admin)
 */
#[Route('/api/dt-filters')]
class DtFilterController extends AbstractController
{
    /** Resolve a stable string owner id (email preferred) */
    private function currentOwnerId(): ?string
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }
        // Prefer email if available, otherwise Symfony user identifier
        if (method_exists($user, 'getEmail') && $user->getEmail()) {
            return (string) $user->getEmail();
        }
        if (method_exists($user, 'getUserIdentifier')) {
            return (string) $user->getUserIdentifier();
        }
        if (method_exists($user, 'getUsername')) {
            return (string) $user->getUsername();
        }
        return null;
    }

    /** Fallback for missing table_key: derive from referer path so the UI still works */
    private function resolveTableKeyOrFallback(Request $req): string
    {
        $tableKey = trim((string) $req->query->get('table_key', ''));
        if ($tableKey !== '') {
            return $tableKey;
        }
        $path = parse_url($req->headers->get('referer') ?? '', PHP_URL_PATH) ?: '/';
        return 'path:' . $path;
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $req, DtSavedFilterRepository $repo): JsonResponse
    {
        $tableKey = $this->resolveTableKeyOrFallback($req);
        $ownerId  = $this->currentOwnerId();

        // Return public filters + my private ones
        $qb = $repo->createQueryBuilder('f')
            ->where('f.tableKey = :t')
            ->andWhere('f.isPublic = 1 OR f.ownerId = :o')
            ->setParameter('t', $tableKey)
            ->setParameter('o', (string) $ownerId)
            ->orderBy('f.isPublic', 'DESC')
            ->addOrderBy('f.name', 'ASC');

        /** @var DtSavedFilter[] $filters */
        $filters = $qb->getQuery()->getResult();

        $out = array_map(static function (DtSavedFilter $f) {
            return [
                'id'           => (int) $f->getId(),
                'table_key'    => $f->getTableKey(),
                'name'         => $f->getName(),
                'is_public'    => $f->isPublic(),
                'owner_id'     => $f->getOwnerId(),
                'details_json' => $f->getDetailsJson(),
                'state_json'   => $f->getStateJson(),
                'created_at'   => $f->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $filters);

        return $this->json($out);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?? [];

        $tableKey    = trim((string) ($data['table_key'] ?? ''));
        $name        = trim((string) ($data['name'] ?? ''));
        $isPublic    = (bool) ($data['is_public'] ?? false);
        $detailsJson = $data['details_json'] ?? null;
        $stateJson   = $data['state_json']   ?? null;

        if ($tableKey === '' || $name === '' || !is_array($detailsJson)) {
            return $this->json(['error' => 'invalid payload'], 400);
        }

        $ownerId = $this->currentOwnerId();

        $f = new DtSavedFilter();
        $f->setTableKey($tableKey);
        $f->setName($name);
        $f->setIsPublic($isPublic);
        // Public → NULL owner; Private → current user
        $f->setOwnerId($isPublic ? null : $ownerId);
        $f->setDetailsJson($detailsJson);
        $f->setStateJson(is_array($stateJson) ? $stateJson : null);

        // If you want to restrict creating public filters to admins, uncomment:
        // if ($isPublic && !$this->isGranted('ROLE_ADMIN')) {
        //     return $this->json(['error' => 'forbidden (public requires admin)'], 403);
        // }

        $em->persist($f);
        $em->flush();

        return $this->json(['id' => (int) $f->getId()], 201);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, DtSavedFilterRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        /** @var DtSavedFilter|null $f */
        $f = $repo->find($id);
        if (!$f) {
            return $this->json(['error' => 'not found'], 404);
        }

        $ownerId = $this->currentOwnerId();
        $mine    = $ownerId !== null && $f->getOwnerId() === $ownerId;

        // Public filters: only admins can delete; Private: owner or admin
        if ($f->isPublic()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                return $this->json(['error' => 'forbidden'], 403);
            }
        } elseif (!$mine && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'forbidden'], 403);
        }

        $em->remove($f);
        $em->flush();

        return $this->json(['ok' => true]);
    }
}
