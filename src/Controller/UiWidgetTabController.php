<?php
namespace App\Controller;

use App\Entity\UiWidgetTab;
use App\Repository\UiWidgetTabRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ui/tabs')]
class UiWidgetTabController extends AbstractController
{
    #[Route('', name: 'api_ui_tabs_index', methods: ['GET'])]
    public function index(UiWidgetTabRepository $repo): JsonResponse
    {
        $userId = (int)$this->getUser()->getId();
        $tabs = $repo->findByOwnerOrdered($userId);

        return $this->json(array_map(fn(UiWidgetTab $t) => [
            'id' => $t->getId(),
            'title' => $t->getTitle(),
            'code' => $t->getCode(),
            'sort_order' => $t->getSortOrder(),
            'is_hidden' => $t->isHidden(),
        ], $tabs));
    }

    #[Route('', name: 'api_ui_tabs_create', methods: ['POST'])]
    public function create(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?: [];
        $title = trim($data['title'] ?? 'New tab');

        $tab = new UiWidgetTab();
        $tab->setOwnerUserId((int)$this->getUser()->getId());
        $tab->setTitle($title);
        $tab->setSortOrder((int)($data['sort_order'] ?? time())); // new goes to end

        $em->persist($tab);
        $em->flush();

        return $this->json(['id' => $tab->getId(), 'title' => $tab->getTitle(), 'sort_order' => $tab->getSortOrder()], 201);
    }

    #[Route('/{id}', name: 'api_ui_tabs_update', methods: ['PATCH'])]
    public function update(int $id, Request $req, UiWidgetTabRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $tab = $repo->find($id);
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (!$tab || $tab->getOwnerUserId() !== (int)$this->getUser()->getId()) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = json_decode($req->getContent(), true) ?: [];
        if (isset($data['title'])) $tab->setTitle(trim((string)$data['title']));
        if (isset($data['sort_order'])) $tab->setSortOrder((int)$data['sort_order']);
        if (isset($data['is_hidden'])) $tab->setIsHidden((bool)$data['is_hidden']);

        $em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/reorder', name: 'api_ui_tabs_reorder', methods: ['POST'])]
    public function reorder(Request $req, UiWidgetTabRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        // body: [{id, sort_order}, ...]
        $rows = json_decode($req->getContent(), true) ?: [];
        $userId = (int)$this->getUser()->getId();

        foreach ($rows as $r) {
            $tab = $repo->find((int)$r['id']);
            if ($tab && $tab->getOwnerUserId() === $userId) {
                $tab->setSortOrder((int)$r['sort_order']);
            }
        }
        $em->flush();
        return $this->json(['ok' => true]);
    }

    #[Route('/{id}', name: 'api_ui_tabs_delete', methods: ['DELETE'])]
    public function delete(int $id, UiWidgetTabRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $tab = $repo->find($id);
        if (!$tab || $tab->getOwnerUserId() !== (int)$this->getUser()->getId()) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $em->remove($tab);
        $em->flush();
        return $this->json(['ok' => true]);
    }
}
