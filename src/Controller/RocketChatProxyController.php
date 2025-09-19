<?php
// src/Controller/RocketChatProxyController.php
namespace App\Controller;

use Psr\Cache\CacheItemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/api/rocketchat')]
class RocketChatProxyController extends AbstractController
{
    private HttpClientInterface $http;
    private CacheInterface $cache;
    private string $baseUrl;
    private string $user;
    private string $pass;

    public function __construct(CacheInterface $cache)
    {
        $this->http = HttpClient::create(['timeout' => 15]);
        $this->cache = $cache;

        $this->baseUrl = rtrim($_ENV['ROCKETCHAT_BASE_URL'] ?? '', '/');
        $this->user    = $_ENV['ROCKETCHAT_BOT_USER'] ?? $_ENV['ROCKETCHAT_USER'] ?? '';
        $this->pass    = $_ENV['ROCKETCHAT_BOT_PASS'] ?? $_ENV['ROCKETCHAT_PASS'] ?? '';
        if (!$this->baseUrl || !$this->user || !$this->pass) {
            throw new \RuntimeException('Rocket.Chat env vars missing: ROCKETCHAT_BASE_URL, ROCKETCHAT_BOT_USER, ROCKETCHAT_BOT_PASS');
        }
    }

    /** Get (or refresh) an auth token + userId and cache it for ~25 minutes. */
    private function getAuth(): array
    {
        return $this->cache->get('rc_login_token', function (CacheItemInterface $item) {
            $item->expiresAfter(1500); // 25 min
            $resp = $this->http->request('POST', $this->baseUrl . '/api/v1/login', [
                'json' => ['user' => $this->user, 'password' => $this->pass],
            ]);
            $data = $resp->toArray(false);
            if (!isset($data['status']) || $data['status'] !== 'success') {
                throw new \RuntimeException('Rocket.Chat login failed');
            }
            return [
                'token'  => $data['data']['authToken'] ?? null,
                'userId' => $data['data']['userId']    ?? null,
            ];
        });
    }

    private function headers(): array
    {
        $auth = $this->getAuth();
        return [
            'X-Auth-Token' => $auth['token'],
            'X-User-Id'    => $auth['userId'],
            'Content-Type' => 'application/json',
        ];
    }

    #[Route('/ddp-token', name: 'rc_ddp_token', methods: ['GET'])]
    public function ddpToken(): JsonResponse
    {
        // Reuse the cached REST login; the authToken works as DDP "resume" token
        $auth = $this->getAuth();
        // WS URL from env, fallback to BASE_URL/websocket
        $ws = $_ENV['ROCKETCHAT_WS_URL'] ?? ($this->baseUrl . '/websocket');

        return $this->json([
            'url'   => $ws,
            'token' => $auth['token'] ?? null,
        ]);
    }

    #[Route('/rooms', name: 'rc_rooms', methods: ['GET'])]
    public function rooms(): JsonResponse
    {
        // Combine public channels + private groups the bot/user can see
        $headers = $this->headers();

        $channels = $this->http->request('GET', $this->baseUrl . '/api/v1/channels.list.joined', [
            'headers' => $headers, 'query' => ['count' => 1000, 'offset' => 0],
        ])->toArray(false);

        $groups = $this->http->request('GET', $this->baseUrl . '/api/v1/groups.list', [
            'headers' => $headers, 'query' => ['count' => 1000, 'offset' => 0],
        ])->toArray(false);

        $out = [];

        if (isset($channels['channels']) && is_array($channels['channels'])) {
            foreach ($channels['channels'] as $c) {
                $out[] = [
                    'id'   => $c['_id'] ?? $c['rid'] ?? null,
                    'name' => $c['name'] ?? '',
                    'type' => 'c', // public
                ];
            }
        }
        if (isset($groups['groups']) && is_array($groups['groups'])) {
            foreach ($groups['groups'] as $g) {
                $out[] = [
                    'id'   => $g['_id'] ?? $g['rid'] ?? null,
                    'name' => $g['name'] ?? ($g['fname'] ?? ''),
                    'type' => 'p', // private
                ];
            }
        }

        // Sort by name asc
        usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $this->json(['rooms' => $out]);
    }

    #[Route('/history', name: 'rc_history', methods: ['GET'])]
    public function history(Request $req): JsonResponse
    {
        $roomId = $req->query->get('roomId');
        $type   = $req->query->get('type', 'c'); // 'c' or 'p'
        $oldest = $req->query->get('oldest');    // ISO 8601
        $latest = $req->query->get('latest');    // ISO 8601
        $limit  = min(max((int)$req->query->get('limit', 1000), 1), 5000);

        if (!$roomId) {
            return $this->json(['error' => 'roomId required'], 400);
        }

        $headers  = $this->headers();
        $endpoint = $type === 'p' ? '/api/v1/groups.history' : '/api/v1/channels.history';

        $all    = [];
        $count  = 200;
        $offset = 0;

        // Paginate until we reach limit or no more messages
        while (count($all) < $limit) {
            $q = [
                'roomId'    => $roomId,
                'count'     => $count,
                'offset'    => $offset,
                'inclusive' => 'true', // include boundary timestamps
            ];
            if ($oldest) $q['oldest'] = $oldest;
            if ($latest) $q['latest'] = $latest;

            $resp = $this->http->request('GET', $this->baseUrl . $endpoint, [
                'headers' => $headers,
                'query'   => $q,
            ])->toArray(false);

            $batch = $resp['messages'] ?? [];
            if (!$batch) break;

            foreach ($batch as $m) {
                // Keep Rocket.Chat’s shape as much as possible so the UI can render rich content
                $one = [
                    '_id' => $m['_id'] ?? null,
                    'id'  => $m['_id'] ?? null,  // convenience for older UI bits
                    'ts'  => $m['ts'] ?? null,
                    'msg' => $m['msg'] ?? '',
                    'text'=> $m['msg'] ?? '',    // compatibility
                    'u'   => [
                        'id'       => $m['u']['_id']      ?? null,
                        'username' => $m['u']['username'] ?? '',
                        'name'     => $m['u']['name']     ?? ($m['u']['username'] ?? ''),
                    ],
                ];

                // Pass-through rich content
                if (!empty($m['attachments']) && is_array($m['attachments'])) {
                    // Whitelist common fields to keep payload moderate
                    $one['attachments'] = array_map(function(array $a): array {
                        return [
                            'title'       => $a['title']       ?? null,
                            'title_link'  => $a['title_link']  ?? null,
                            'text'        => $a['text']        ?? ($a['description'] ?? null),
                            'description' => $a['description'] ?? null,
                            'image_url'   => $a['image_url']   ?? null,
                            'thumb_url'   => $a['thumb_url']   ?? null,
                        ];
                    }, $m['attachments']);
                }
                if (!empty($m['urls']) && is_array($m['urls'])) {
                    // Keep as-is (UI reads meta.og* fields)
                    $one['urls'] = $m['urls'];
                }

                $all[] = $one;
                if (count($all) >= $limit) break 2;
            }

            if (isset($resp['total']) && ($offset + $count) >= (int)$resp['total']) break;
            $offset += $count;
            if ($offset > 5000) break; // hard stop
        }

        // sort ascending timestamp
        usort($all, function($a, $b) {
            return strcmp($a['ts'] ?? '', $b['ts'] ?? '');
        });

        return $this->json([
            'roomId'   => $roomId,
            'count'    => count($all),
            'messages' => $all,
        ]);
    }
}
