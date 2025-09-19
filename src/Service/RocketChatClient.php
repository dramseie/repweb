<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RocketChatClient
{
    public function __construct(
        private HttpClientInterface $http,
        private RequestStack $requestStack,
        private string $baseUrl,
        private string $botUser,
        private string $botPass
    ) {}

    private function session()
    {
        $s = $this->requestStack->getSession();
        if (!$s) throw new \RuntimeException('Session not started');
        return $s;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /** Login (if needed) and keep token in session */
    public function ensureToken(): array
    {
        $sess = $this->session();
        $tok  = $sess->get('rc.auth');
        if ($tok && ($tok['authToken'] ?? null) && ($tok['userId'] ?? null)) {
            return $tok;
        }

        $resp = $this->http->request('POST', $this->getBaseUrl().'/api/v1/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => ['user' => $this->botUser, 'password' => $this->botPass],
        ]);
        $data = $resp->toArray(false);
        if (($data['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Rocket.Chat login failed: '.json_encode($data));
        }

        $tok = [
            'userId'    => $data['data']['userId']    ?? null,
            'authToken' => $data['data']['authToken'] ?? null,
        ];
        if (!$tok['userId'] || !$tok['authToken']) {
            throw new \RuntimeException('Login returned no token/userId');
        }
        $sess->set('rc.auth', $tok);
        return $tok;
    }

    public function clearToken(): void
    {
        $this->session()->remove('rc.auth');
    }

    private function authHeaders(): array
    {
        $tok = $this->ensureToken();
        return [
            'X-User-Id'    => $tok['userId'],
            'X-Auth-Token' => $tok['authToken'],
        ];
    }

    /** Resolve a room _id by its human name (public OR private). Caches in session. */
    public function getRoomIdByName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') return null;

        $sess = $this->session();
        $cacheKey = 'rc.roomId.name.'.$name;
        if ($sess->has($cacheKey)) {
            return $sess->get($cacheKey);
        }

        $base = $this->getBaseUrl();

        // 1) Public channels
        $r = $this->http->request('GET', $base.'/api/v1/channels.info', [
            'headers' => $this->authHeaders(),
            'query'   => ['roomName' => $name],
        ]);
        $j = $r->toArray(false);
        if (($j['success'] ?? false) && !empty($j['channel']['_id'])) {
            $sess->set($cacheKey, $j['channel']['_id']);
            return $j['channel']['_id'];
        }

        // 2) Private groups
        $r = $this->http->request('GET', $base.'/api/v1/groups.info', [
            'headers' => $this->authHeaders(),
            'query'   => ['roomName' => $name],
        ]);
        $j = $r->toArray(false);
        if (($j['success'] ?? false) && !empty($j['group']['_id'])) {
            $sess->set($cacheKey, $j['group']['_id']);
            return $j['group']['_id'];
        }

        // 3) Fallback (newer RC): rooms.info by name
        $r = $this->http->request('GET', $base.'/api/v1/rooms.info', [
            'headers' => $this->authHeaders(),
            'query'   => ['roomName' => $name],
        ]);
        $j = $r->toArray(false);
        if (($j['success'] ?? false) && !empty($j['room']['_id'])) {
            $sess->set($cacheKey, $j['room']['_id']);
            return $j['room']['_id'];
        }

        return null; // not found or no access
    }
}
