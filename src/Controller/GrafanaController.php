<?php
        namespace App\Controller;
        use App\Entity\AppUser; use App\Service\GrafanaService;
        use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
        use Symfony\Component\Routing\Annotation\Route; use Symfony\Component\HttpFoundation\Response;
        use Symfony\Component\HttpFoundation\JsonResponse; use Symfony\Contracts\HttpClient\HttpClientInterface;
        class GrafanaController extends AbstractController {
          public function __construct(private HttpClientInterface $http) {}
          #[Route('/grafana', name:'grafana_index')] public function index(): Response { return $this->render('grafana/index.html.twig'); }
          #[Route('/api/grafana/dashboards', name:'grafana_dashboards_api')] public function dashboards(GrafanaService $svc): JsonResponse {
            /** @var AppUser|null $u */ $u=$this->getUser(); $roles=$u?->getRoles()??['ROLE_USER']; $allow=$u?->getGrafanaDashboards();
            $list=$svc->listDashboardsForUser($roles,$allow); $list=array_map(fn($d)=>$d+['url'=>$svc->buildEmbedUrl($d['uid'])],$list); return $this->json($list);
          }
          #[Route('/grafana/{uid}', name:'grafana_view')] public function view(string $uid, GrafanaService $svc): Response {
            return $this->render('grafana/view.html.twig',['embedUrl'=>$svc->buildEmbedUrl($uid),'uid'=>$uid]);
          }
          #[Route('/grafana/embed/{uid}', name:'grafana_embed')] public function embed(string $uid, GrafanaService $svc): Response {
            $base=rtrim($svc->grafanaBase(),'/'); $org=$svc->grafanaOrg(); $url=sprintf('%s/d/%s?orgId=%s&kiosk',$base,urlencode($uid),urlencode($org));
            $u=$this->getUser(); $uTok=$u && method_exists($u,'getGrafanaToken') ? $u->getGrafanaToken():null; $tok=$uTok ?: ($_ENV['GRAFANA_API_TOKEN'] ?? '');
            if(!$tok){ return new Response('Grafana token not configured',500); }
            $r=$this->http->request('GET',$url,['headers'=>['Authorization'=>'Bearer '.$tok]]);
$headers = $r->getHeaders(false);
$contentType = isset($headers['content-type'][0]) ? $headers['content-type'][0] : 'text/html';

return new Response(
    $r->getContent(false),
    $r->getStatusCode(),
    ['Content-Type' => $contentType]
);

          }
        }
        