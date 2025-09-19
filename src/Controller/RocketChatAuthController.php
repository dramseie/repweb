<?php
namespace App\Controller;

use App\Service\RocketChatClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/rocketchat', name: 'api_rocketchat_')]
class RocketChatAuthController extends AbstractController
{
    public function __construct(private RocketChatClient $rc) {}

    #[Route('/config', name: 'config', methods: ['GET'])]
    public function config(Request $req, LoggerInterface $logger): JsonResponse
    {
        try {
            // Priority: explicit roomId param -> explicit roomName param -> env defaults
            $roomId   = trim((string)$req->query->get('roomId', ''));
            $roomName = trim((string)$req->query->get('roomName', ''));

            if ($roomId === '') {
                if ($roomName === '') {
                    $roomName = $_ENV['ROCKETCHAT_DEFAULT_ROOM_NAME'] ?? '';
                }
                if ($roomName !== '') {
                    $roomId = $this->rc->getRoomIdByName($roomName) ?? '';
                }
            }

            if ($roomId === '') {
                $roomId = $_ENV['ROCKETCHAT_DEFAULT_ROOM_ID'] ?? '';
            }

            if ($roomId === '') {
                return $this->json(['error' => 'No roomId / roomName provided and no default configured'], 400);
            }

            $tok = $this->rc->ensureToken();
            $url = $_ENV['ROCKETCHAT_WS_URL'] ?? ($this->rc->getBaseUrl().'/websocket');

            $out = [
                'url'         => $url,
                'userId'      => $tok['userId'] ?? null,
                'token'       => $tok['authToken'] ?? null,
                'roomId'      => $roomId,
                'maxMessages' => 20,
            ];
            if (!$out['userId'] || !$out['token']) {
                return $this->json(['error' => 'No token from Rocket.Chat login'], 502);
            }

            $resp = $this->json($out);
            $resp->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $resp->headers->set('Pragma', 'no-cache');
            return $resp;
        } catch (\Throwable $e) {
            $logger->error('rocketchat/config failed', ['ex' => $e]);
            return $this->json(['error' => 'Config generation failed', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $this->rc->clearToken();
        return $this->json(['ok' => true]);
    }
}
