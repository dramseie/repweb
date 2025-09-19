<?php
namespace App\Controller;

use App\Entity\UiWidgetTab;
use App\Repository\UiWidgetTabRepository;
use App\Service\UserTabsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/widgets/tabs', name: 'api_widgets_tabs_')]
class WidgetsTabsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UiWidgetTabRepository $repo,
        private UserTabsManager $mgr
    ) {}

	#[Route('', name: 'list', methods: ['GET'])]
	public function list(): JsonResponse
	{
		$uid = (int)$this->getUser()->getId();
		$tabs = $this->repo->forUser($uid); // ordered in the repo
		return $this->json(array_map(fn(UiWidgetTab $t) => [
			'id'         => $t->getId(),
			'title'      => $t->getTitle(),
			'sort_order' => $t->getSortOrder(),
			'is_hidden'  => $t->isHidden(),
		], $tabs));
	}


    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $r): JsonResponse
    {
        $d = json_decode($r->getContent(), true) ?: [];
        $t = (new UiWidgetTab())
            ->setOwnerUserId((int)$this->getUser()->getId())
            ->setTitle($d['title'] ?? 'New tab')
            ->setPosition((int)($d['position'] ?? 999))
            ->setLayoutJson(['version'=>1, 'items'=>[], 'layouts'=>[]]);

        $this->em->persist($t); $this->em->flush();
        return $this->json($t->toListArray());
    }

    #[Route('/reset-to-defaults', name: 'reset', methods: ['POST'])]
    public function reset(): JsonResponse
    {
        $uid = (int)$this->getUser()->getId();
        foreach ($this->repo->forUser($uid) as $tab) {
            $this->em->remove($tab);
        }
        $this->em->flush();
        $this->mgr->ensureTabsFor($this->getUser());
        return $this->json(['ok'=>true]);
    }

    // ---------- Per-tab endpoints used by WidgetsDashboard ----------
    #[Route('/{id<\d+>}/defs', name: 'defs', methods: ['GET'])]
    public function defs(int $id): JsonResponse
    {
        $tab = $this->repo->find($id);
        $this->denyUnlessOwner($tab);
        return $this->json(UserTabsManager::widgetDefs());
    }

    #[Route('/{id<\d+>}/layout', name: 'layout_get', methods: ['GET'])]
    public function layoutGet(int $id): JsonResponse
    {
        $tab = $this->repo->find($id);
        $this->denyUnlessOwner($tab);
        $json = $tab->getLayoutJson() ?? ['version'=>1,'items'=>[],'layouts'=>[]];
        return $this->json($json);
    }

    #[Route('/{id<\d+>}/layout', name: 'layout_save', methods: ['POST'])]
    public function layoutSave(int $id, Request $r): JsonResponse
    {
        $tab = $this->repo->find($id);
        $this->denyUnlessOwner($tab);
        $payload = json_decode($r->getContent(), true) ?: ['version'=>1,'items'=>[],'layouts'=>[]];
        $tab->setLayoutJson($payload); $tab->touch();
        $this->em->flush();
        return $this->json(['ok'=>true]);
    }

    private function denyUnlessOwner(?UiWidgetTab $tab): void
    {
        if (!$tab || $tab->getOwnerUserId() !== (int)$this->getUser()->getId()) {
            throw $this->createNotFoundException();
        }
    }
}
