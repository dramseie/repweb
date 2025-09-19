<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Per-user Rocket.Chat subscriptions (channels to watch/notify)
 */
#[Route('/api/rocketchat')]
class RocketChatUserPrefController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('/subscriptions', name: 'rc_subscriptions_get', methods: ['GET'])]
    public function getSubs(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'auth required'], 401);

        $rows = $this->db->fetchAllAssociative(
            'SELECT room_id, room_name, room_type, notify FROM user_rc_subscriptions WHERE user_id = ? ORDER BY room_name ASC',
            [$user->getId()]
        );

        // Fallback: if none saved yet, seed with default room name from env (resolved at frontend)
        return $this->json([
            'subscriptions' => $rows,
            'defaults' => [
                'defaultRoomName' => $_ENV['ROCKETCHAT_DEFAULT_ROOM_NAME'] ?? 'repweb',
            ],
        ]);
    }

    #[Route('/subscriptions', name: 'rc_subscriptions_put', methods: ['PUT','POST'])]
    public function setSubs(Request $req): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json(['error' => 'auth required'], 401);

        $payload = json_decode($req->getContent() ?: '[]', true);
        $items = is_array($payload) ? $payload : ($payload['subscriptions'] ?? []);
        if (!is_array($items)) $items = [];

        $this->db->beginTransaction();
        try {
            $this->db->executeStatement('DELETE FROM user_rc_subscriptions WHERE user_id = ?', [$user->getId()]);
            foreach ($items as $it) {
                $this->db->insert('user_rc_subscriptions', [
                    'user_id'   => $user->getId(),
                    'room_id'   => (string)($it['room_id'] ?? ''),
                    'room_name' => (string)($it['room_name'] ?? ''),
                    'room_type' => ($it['room_type'] ?? 'c') === 'p' ? 'p' : 'c',
                    'notify'    => !empty($it['notify']) ? 1 : 0,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return $this->json(['error' => 'save failed', 'detail' => $e->getMessage()], 500);
        }

        return $this->json(['ok' => true, 'count' => count($items)]);
    }
}
