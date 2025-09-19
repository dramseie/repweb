<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class NiFiClient
{
    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl,
        private string $user,
        private string $pass,
        private bool $verifyTls
    ) {}

    public static function fromEnv(HttpClientInterface $client): self {
        return new self(
            $client,
            rtrim($_ENV['NIFI_BASE_URL'] ?? '', '/'), // e.g. https://repweb.ramseier.com:8443/nifi-api
            $_ENV['NIFI_USER'] ?? '',
            $_ENV['NIFI_PASS'] ?? '',
            (bool) (int) ($_ENV['NIFI_VERIFY_TLS'] ?? '1')
        );
    }

    private ?string $bearer = null;
    private ?int $bearerIssuedAt = null;

    /** Common, version-safe options (only supported keys) */
    private function opts(array $extra = []): array
    {
        $readSeconds = (float)($_ENV['NIFI_READ_TIMEOUT'] ?? 30);   // idle per chunk
        $maxSeconds  = (float)($_ENV['NIFI_MAX_DURATION'] ?? 45);   // whole request cap

        return $extra + [
            'verify_peer'  => $this->verifyTls,
            'verify_host'  => $this->verifyTls,
            'timeout'      => $readSeconds,
            'max_duration' => $maxSeconds,
            'headers'      => [
                'Accept'     => 'application/json',
                'User-Agent' => 'repweb-nifi-proxy/1.0',
            ],
        ];
    }

    /** Obtain & cache JWT from /access/token (single-user-provider) */
	private function getToken(bool $forceRefresh = false): string
	{
		$ttl = (int)($_ENV['NIFI_TOKEN_TTL'] ?? 0);
		if (!$forceRefresh && $this->bearer) {
			if ($ttl > 0 && $this->bearerIssuedAt && (time() - $this->bearerIssuedAt) < max(60, (int)($ttl * 0.9))) {
				return $this->bearer;
			}
			if ($ttl <= 0) return $this->bearer;
		}

		$resp = $this->client->request('POST', $this->baseUrl.'/access/token', $this->opts([
			'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
			'body'    => http_build_query(['username' => $this->user, 'password' => $this->pass]),
		]));

		$status = $resp->getStatusCode(false);
		$tok    = trim($resp->getContent(false));

		// ✅ Accept any 2xx + non-empty body as success
		if ($status < 200 || $status >= 300 || $tok === '') {
			throw new \RuntimeException('Failed to obtain NiFi token: HTTP '.$status.' — '.substr($tok, 0, 200));
		}

		$this->bearer = $tok;
		$this->bearerIssuedAt = time();
		return $tok;
	}


    /** Request helper (Bearer; retries once on 401 or network error) */
    private function req(string $method, string $path): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        try {
            $resp = $this->client->request($method, $url, $this->opts([
                'auth_bearer' => $this->getToken(false),
            ]));

            if ($resp->getStatusCode(false) === 401) {
                // refresh token and retry once
                $resp = $this->client->request($method, $url, $this->opts([
                    'auth_bearer' => $this->getToken(true),
                ]));
            }

            return $resp->toArray(false);
        } catch (\Throwable $e) {
            // one simple retry (e.g., transient TLS/socket hiccup)
            $resp = $this->client->request($method, $url, $this->opts([
                'auth_bearer' => $this->getToken(true),
            ]));
            return $resp->toArray(false);
        }
    }

    /** -------- Public NiFi helpers -------- */

    public function getRootId(): string {
        $root = $this->req('GET', 'process-groups/root');
        return $root['id'] ?? $root['component']['id'] ?? 'root';
    }

    /** @return array<int,array{id:string,name:string,parentId:?string}> */
    public function listProcessGroups(string $parentId): array {
        $data = $this->req('GET', "process-groups/$parentId/process-groups");
        $groups = [];
        foreach (($data['processGroups'] ?? []) as $pg) {
            $groups[] = [
                'id'       => $pg['id'],
                'name'     => $pg['component']['name'] ?? $pg['component'] ?? $pg['id'],
                'parentId' => $parentId,
            ];
        }
        return $groups;
    }

    public function getProcessGroupStatus(string $pgId): array {
        $s = $this->req('GET', "flow/process-groups/$pgId/status");
        $snap = $s['processGroupStatus']['aggregateSnapshot'] ?? [];
        return [
            'runningCount'       => $snap['runningCount']       ?? 0,
            'stoppedCount'       => $snap['stoppedCount']       ?? 0,
            'invalidCount'       => $snap['invalidCount']       ?? 0,
            'disabledCount'      => $snap['disabledCount']      ?? 0,
            'activeThreadCount'  => $snap['activeThreadCount']  ?? 0,
            'flowFilesQueued'    => $snap['flowFilesQueued']    ?? 0,
        ];
    }

    /** Recursively walk tree starting at $startId */
    public function collectAllWithStatus(string $startId = 'root'): array {
        if ($startId === 'root') $startId = $this->getRootId();
        $stack = [$startId];
        $nodes = [];

        $rootMeta = $this->req('GET', "process-groups/$startId");
        $nodes[$startId] = [
            'id'       => $startId,
            'name'     => $rootMeta['component']['name'] ?? 'NiFi Flow',
            'parentId' => null,
            'status'   => $this->getProcessGroupStatus($startId),
        ];

        while ($stack) {
            $pid = array_pop($stack);
            foreach ($this->listProcessGroups($pid) as $child) {
                $nodes[$child['id']] = $child + ['status' => $this->getProcessGroupStatus($child['id'])];
                $stack[] = $child['id'];
            }
        }
        return array_values($nodes);
    }
}
