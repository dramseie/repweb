<?php
namespace App\Controller;

use App\Repository\RestConnectorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Server-side proxy for exploring REST APIs (no CORS issues).
 * - Merges connector default headers with ad-hoc headers.
 * - SAME-HOST calls: forward session cookies (+ optional CSRF) and release session lock.
 */
#[Route('/api/resttool', name: 'api_resttool_')]
class RestApiProxyController extends AbstractController
{
    public function __construct(private RestConnectorRepository $connRepo) {}

    #[Route('/fetch', name: 'fetch', methods: ['POST'])]
    public function fetch(Request $req): JsonResponse
    {
        $payload = json_decode($req->getContent() ?: '{}', true) ?: [];

        $connectorId = (int)($payload['connectorId'] ?? 0);
        $path        = (string)($payload['path'] ?? '/');
        $method      = strtoupper((string)($payload['method'] ?? 'GET'));
        $query       = (array)($payload['query'] ?? []);
        $addHeaders  = (array)($payload['headers'] ?? []);
        $rawBody     = $payload['body'] ?? null; // string|array|null

        $connector = $this->connRepo->find($connectorId);
        if (!$connector) {
            return $this->json(['error' => 'Connector not found'], 404);
        }

        $base = $connector->getBaseUrl();
        $baseHost    = parse_url($base, PHP_URL_HOST) ?: '';
        $currentHost = $req->getHost() ?: '';
        $isSameHost  = $baseHost && strcasecmp($baseHost, $currentHost) === 0;

        // Block private ranges unless we're calling back the same host
        if (!$isSameHost && preg_match('#^(https?://)(localhost|127\.0\.0\.1|10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)#i', $base)) {
            return $this->json(['error' => 'Blocked base URL (local/private).'], 403);
        }

        $url = rtrim($base, '/') . '/' . ltrim($path, '/');

        // ✅ Prevent deadlock: release Symfony's session file lock before self-call
        if ($isSameHost && $req->hasSession()) {
            $session = $req->getSession();
            if ($session->isStarted()) {
                $session->save();
            }
        }

        $client = HttpClient::create();

        // Merge headers (connector defaults < ad-hoc)
        $headers = array_merge($connector->getDefaultHeaders(), $addHeaders);
        $headers = array_filter($headers, fn($v) => $v !== null && $v !== '');

        if ($isSameHost) {
            // Forward browser cookies so your API sees the logged-in session
            if ($cookieHeader = $req->headers->get('Cookie')) {
                $headers['Cookie'] = $cookieHeader;
            } else {
                $pairs = [];
                foreach ($req->cookies->all() as $ck => $cv) {
                    $pairs[] = $ck . '=' . rawurlencode((string)$cv);
                }
                if ($pairs) $headers['Cookie'] = implode('; ', $pairs);
            }
            $headers['X-Requested-With'] = $headers['X-Requested-With'] ?? 'XMLHttpRequest';
            if ($csrf = $req->headers->get('X-CSRF-TOKEN')) {
                $headers['X-CSRF-TOKEN'] = $csrf;
            }
        }

        $options = [
            'headers'      => $headers,
            'query'        => $query,
            'timeout'      => 20, // socket inactivity
            'max_duration' => 30, // total cap
            // 'max_redirects' => 5,
        ];

        if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
            if (is_array($rawBody)) {
                $options['json'] = $rawBody;
            } elseif (is_string($rawBody) && $rawBody !== '') {
                $options['body'] = $rawBody;
            }
        }

        try {
            $resp = $client->request($method, $url, $options);
            $status      = $resp->getStatusCode();
            $headersOut  = $resp->getHeaders(false);
            $contentType = $headersOut['content-type'][0] ?? 'application/octet-stream';
            $raw         = $resp->getContent(false);

            $out = [
                'status'  => $status,
                'headers' => $headersOut,
                'url'     => $url,
                'method'  => $method,
                'query'   => $query,
            ];

            if (stripos($contentType, 'application/json') !== false) {
                $out['json'] = json_decode($raw, true);
            } else {
                $out['text'] = mb_substr($raw, 0, 250000);
                $out['contentType'] = $contentType;
            }

            return $this->json($out);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Request failed',
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
