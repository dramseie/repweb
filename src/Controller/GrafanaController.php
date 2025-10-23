<?php

namespace App\Controller;

use App\Entity\AppUser;
use App\Service\GrafanaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GrafanaController extends AbstractController
{
    #[Route('/grafana', name: 'grafana_index', methods: ['GET'])]
    public function index(GrafanaService $svc): Response
    {
        return $this->render('grafana/index.html.twig', [
            'grafana_org_id'   => $svc->grafanaOrg(),
            'grafana_base_url' => $svc->grafanaBase(),
        ]);
    }

    #[Route('/api/grafana/folders', name: 'grafana_folders_api', methods: ['GET'])]
    public function folders(GrafanaService $svc): JsonResponse
    {
        try {
            return $this->json(['folders' => $svc->listFolders()]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/grafana/dashboards', name: 'grafana_dashboards_api', methods: ['GET'])]
    public function dashboards(Request $req, GrafanaService $svc): JsonResponse
    {
        try {
            /** @var AppUser|null $u */
            $u = $this->getUser();
            $roles = $u?->getRoles() ?? ['ROLE_USER'];

            $allow = null;
            if ($u && method_exists($u, 'getGrafanaDashboards')) {
                $allow = $u->getGrafanaDashboards();
            }

            $folderIds = [];
            $param = (string)$req->query->get('folderIds', '');
            if ($param !== '') {
                foreach (explode(',', $param) as $p) {
                    $p = trim($p);
                    if ($p !== '' && ctype_digit($p)) $folderIds[] = (int)$p;
                }
            }

            $list = $svc->listDashboardsForUser($roles, $allow, $folderIds ?: null);

            foreach ($list as &$d) {
                $d['url'] = $svc->buildEmbedUrl($d['uid']);
            }

            return $this->json(['dashboards' => $list]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/grafana/dashboards/{uid}/panels', name: 'grafana_dashboard_panels_api', methods: ['GET'])]
    public function panels(string $uid, GrafanaService $svc): JsonResponse
    {
        try {
            return $this->json($svc->dashboardPanels($uid));
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
	
	// ...

	#[Route('/api/grafana/dashboards/{uid}/variables', name: 'grafana_dashboard_vars_api', methods: ['GET'])]
	public function variables(string $uid, GrafanaService $svc): JsonResponse
	{
		try {
			$vars = $svc->dashboardVariables($uid);
			return $this->json(['uid' => $uid, 'variables' => $vars]);
		} catch (\Throwable $e) {
			return $this->json(['error' => $e->getMessage()], 500);
		}
	}
	
}
