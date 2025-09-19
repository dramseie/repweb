<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/checkmk', name: 'api_checkmk_')]
class CheckmkApiController extends AbstractController
{
    public function __construct(private HttpClientInterface $http) {}

    private function baseUrl(): string
    {
        $base = rtrim((string)($_ENV['CHECKMK_BASE_URL'] ?? ''), '/');
        $site = trim((string)($_ENV['CHECKMK_SITE'] ?? 'monitoring'), '/');
        if ($base === '') throw new \RuntimeException('CHECKMK_BASE_URL is not set');
        return "$base/$site/check_mk/api/1.0";
    }

    /** Build URL with repeated ?columns=... (NOT columns[0]=...) and NO implicit limit/offset */
    private function buildUrl(string $path, array $params = [], array $columns = []): string
    {
        // Do not inject limit/offset by default — some CMK routes 400 with them
        $params = $params ?: [];
        unset($params['columns']);

        $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        foreach ($columns as $c) {
            $qs .= ($qs === '' ? '' : '&') . 'columns=' . rawurlencode($c);
        }
        return $this->baseUrl() . $path . ($qs ? ('?' . $qs) : '');
    }

    /** Return possible auth header sets (Bearer first, then Basic) */
    private function authHeaderOptions(): array
    {
        $opts = [];
        $common = ['Accept' => 'application/json'];

        $token = trim((string)($_ENV['CHECKMK_TOKEN'] ?? ''));
        if ($token !== '') $opts[] = $common + ['Authorization' => "Bearer {$token}"];

        $user = (string)($_ENV['CHECKMK_USER'] ?? '');
        $pass = (string)($_ENV['CHECKMK_PASSWORD'] ?? '');
        if ($user !== '' && $pass !== '') {
            $opts[] = $common + ['Authorization' => 'Basic ' . base64_encode("$user:$pass")];
        }
        if (!$opts) throw new \RuntimeException('Provide CHECKMK_TOKEN or CHECKMK_USER+CHECKMK_PASSWORD');
        return $opts;
    }

    /**
     * Try primary & fallback paths and each auth header until one succeeds.
     * Returns [rows, meta] where meta shows url/auth/status/bodySnippet for visibility.
     */
    private function getListWithMeta(
        string $path,
        array $params = [],
        array $columns = [],
        ?string $fallbackPath = null
    ): array {
        $paths = array_filter([$path, $fallbackPath]);
        $authOptions = $this->authHeaderOptions();

        $attempts = [];
        foreach ($paths as $p) {
            $url = $this->buildUrl($p, $params, $columns);
            foreach ($authOptions as $headers) {
                $authKind = str_starts_with(($headers['Authorization'] ?? ''), 'Bearer ') ? 'bearer' : 'basic';
                try {
                    $resp = $this->http->request('GET', $url, ['headers' => $headers]);
                    $code = $resp->getStatusCode();
                    $text = $resp->getContent(false);
                    $bodySnippet = substr($text, 0, 300);
                    $data = json_decode($text, true);

                    $attempts[] = ['url' => $url, 'auth' => $authKind, 'status' => $code, 'body' => $bodySnippet];

                    if ($code >= 200 && $code < 300 && is_array($data)) {
                        if (isset($data['value']) && is_array($data['value'])) return [$data['value'], ['used' => end($attempts), 'attempts' => $attempts]];
                        if (isset($data['data'])  && is_array($data['data']))  return [$data['data'],  ['used' => end($attempts), 'attempts' => $attempts]];
                        if (isset($data['items']) && is_array($data['items'])) return [$data['items'], ['used' => end($attempts), 'attempts' => $attempts]];
                        if (array_is_list($data)) return [$data, ['used' => end($attempts), 'attempts' => $attempts]];
                        return [[], ['used' => end($attempts), 'attempts' => $attempts, 'note' => '2xx but unexpected payload']];
                    }
                } catch (\Throwable $e) {
                    $attempts[] = ['url' => $url, 'auth' => $authKind, 'status' => 'EXC', 'error' => $e->getMessage()];
                }
            }
        }
        return [[], ['used' => null, 'attempts' => $attempts]];
    }

    private function getList(string $path, array $params = [], array $columns = [], ?string $fallbackPath = null): array
    {
        [$rows, $_] = $this->getListWithMeta($path, $params, $columns, $fallbackPath);
        return $rows;
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        try {
            // HOSTS with state — NO limit/offset
            $hostCols = ['name','state','num_services','num_services_ok','num_services_warn','num_services_crit','num_services_unknown'];
            $hostRows = $this->getList('/domain-types/host/collections/all', [], $hostCols, '/objects/host');

            $hTotal = count($hostRows);
            $hUp = $hDown = $hUnreach = 0;
            foreach ($hostRows as $h) {
                $st = $h['extensions']['state'] ?? $h['state'] ?? null;
                $st = is_string($st) ? (int)$st : $st;
                if ($st === 0) $hUp++;
                elseif ($st === 1) $hDown++;
                elseif ($st === 2) $hUnreach++;
            }

            // SERVICES with state/output — NO limit/offset
            $svcCols = ['host_name','description','state','plugin_output'];
            $svcRows = $this->getList('/domain-types/service/collections/all', [], $svcCols, '/objects/service');

            $sTotal = count($svcRows);
            $sOk = $sWarn = $sCrit = $sUnk = 0;
            $problems = [];
            foreach ($svcRows as $s) {
                $st = $s['extensions']['state'] ?? $s['state'] ?? 0;
                $st = is_string($st) ? (int)$st : $st;
                if ($st === 0) $sOk++;
                elseif ($st === 1) { $sWarn++; $problems[] = $s; }
                elseif ($st === 2) { $sCrit++; $problems[] = $s; }
                elseif ($st === 3) { $sUnk++;  $problems[] = $s; }
            }

            usort($problems, function($a, $b) {
                $pa = (int)($a['extensions']['state'] ?? $a['state'] ?? 0);
                $pb = (int)($b['extensions']['state'] ?? $b['state'] ?? 0);
                $prio = [2=>3, 1=>2, 3=>1, 0=>0];
                return ($prio[$pb] ?? 0) <=> ($prio[$pa] ?? 0);
            });

            $top = array_map(function($s) {
                return [
                    'host'   => $s['extensions']['host_name']   ?? $s['host_name']   ?? '',
                    'service'=> $s['extensions']['description'] ?? $s['description'] ?? '',
                    'state'  => (int)($s['extensions']['state'] ?? $s['state'] ?? 0),
                    'output' => $s['extensions']['plugin_output']?? $s['plugin_output']?? '',
                ];
            }, array_slice($problems, 0, 20));

            return $this->json([
                'ts' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'hosts' => ['total'=>$hTotal, 'up'=>$hUp, 'down'=>$hDown, 'unreach'=>$hUnreach],
                'services' => ['total'=>$sTotal, 'ok'=>$sOk, 'warn'=>$sWarn, 'crit'=>$sCrit, 'unknown'=>$sUnk],
                'problems' => $top,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /** Debug endpoint with body snippet to understand 4xx/5xx */
    #[Route('/debug/{what}', name: 'debug', requirements: ['what' => 'hosts|services'], methods: ['GET'])]
    public function debug(string $what): JsonResponse
    {
        $cols = $what === 'hosts'
            ? ['name','state','num_services','num_services_ok','num_services_warn','num_services_crit','num_services_unknown']
            : ['host_name','description','state','plugin_output'];

        [$rows, $meta] = $this->getListWithMeta(
            $what === 'hosts' ? '/domain-types/host/collections/all' : '/domain-types/service/collections/all',
            [],
            $cols,
            $what === 'hosts' ? '/objects/host' : '/objects/service'
        );

        return $this->json([
            'meta' => $meta,
            'count' => count($rows),
            'first' => $rows[0] ?? null,
        ]);
    }

    #[Route('/problems', name: 'problems', methods: ['GET'])]
    public function problems(Request $req): JsonResponse
    {
        try {
            // No implicit limit; CMK will default (usually >= 1000)
            [$svcRows, $meta] = $this->getListWithMeta(
                '/domain-types/service/collections/all',
                [],
                ['host_name','description','state','plugin_output'],
                '/objects/service'
            );

            $problems = array_values(array_filter($svcRows, function($s) {
                $st = (int)($s['extensions']['state'] ?? $s['state'] ?? 0);
                return $st !== 0;
            }));

            $mapped = array_map(function($s) {
                return [
                    'host'   => $s['extensions']['host_name']   ?? $s['host_name']   ?? '',
                    'service'=> $s['extensions']['description'] ?? $s['description'] ?? '',
                    'state'  => (int)($s['extensions']['state'] ?? $s['state'] ?? 0),
                    'output' => $s['extensions']['plugin_output']?? $s['plugin_output']?? '',
                ];
            }, $problems);

            return $this->json(['meta' => $meta, 'items' => $mapped]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
