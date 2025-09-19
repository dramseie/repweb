<?php
namespace App\Controller;

use App\Service\RouteTreeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RouteDiagController extends AbstractController
{
    public function __construct(
        private RouteTreeService $routes,
        private HttpClientInterface $http
    ) {}

    #[Route(path: '/_diag/routes', name: 'diag_routes_page', methods: ['GET'])]
    public function page(): Response
    {
        return $this->render('diag/routes.html.twig');
    }

    #[Route(path: '/_diag/api/routes', name: 'diag_routes_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->routes->listRoutes());
    }

    #[Route(path: '/_diag/api/check', name: 'diag_routes_check', methods: ['POST'])]
    public function check(Request $req): JsonResponse
    {
        $payload = json_decode($req->getContent() ?: '{}', true);
        $targets = $payload['targets'] ?? []; // array of route names to check; if empty => check all
        $timeout = (float)($payload['timeout'] ?? 5.0);
        $method  = strtoupper($payload['method'] ?? 'HEAD'); // Try HEAD first; fallback to GET per route

        $data = $this->routes->listRoutes()['flat'];
        if ($targets) {
            $data = array_values(array_filter($data, fn($r) => in_array($r['name'], $targets, true)));
        }

        $results = [];
        foreach ($data as $r) {
            if (!$r['hasSafe'] || !$r['canGenerate'] || empty($r['url'])) {
                $results[] = [
                    'name'   => $r['name'],
                    'url'    => $r['url'],
                    'ok'     => false,
                    'status' => null,
                    'timeMs' => null,
                    'err'    => $r['canGenerate'] ? 'NotSafeOrNoURL' : 'CannotGenerateURL',
                ];
                continue;
            }

            $start = microtime(true);
            $ok = false; $status = null; $err = null;

            try {
                // Prefer HEAD; if 405/501/403, we’ll try GET
                $resp = $this->http->request($method, $r['url'], ['timeout' => $timeout, 'max_redirects' => 3]);
                $status = $resp->getStatusCode();
                $ok = $status >= 200 && $status < 400;
                if (!$ok && $method === 'HEAD' && in_array($status, [403,405,501], true)) {
                    $resp = $this->http->request('GET', $r['url'], ['timeout' => $timeout, 'max_redirects' => 3]);
                    $status = $resp->getStatusCode();
                    $ok = $status >= 200 && $status < 400;
                }
            } catch (TransportException $e) {
                $err = 'Transport: ' . $e->getMessage();
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }

            $results[] = [
                'name'   => $r['name'],
                'url'    => $r['url'],
                'ok'     => $ok,
                'status' => $status,
                'timeMs' => (int) round((microtime(true) - $start) * 1000),
                'err'    => $err,
            ];
        }

        // Sort by ok desc, then status, then name
        usort($results, fn($a,$b) => [$b['ok'],$a['status'] ?? 0,$a['name']] <=> [$a['ok'],$b['status'] ?? 0,$b['name']]);

        return $this->json(['results' => $results, 'count' => count($results)]);
    }
}
