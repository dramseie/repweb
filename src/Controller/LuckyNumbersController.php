<?php
// src/Controller/LuckyNumbersController.php
namespace App\Controller;

use App\Service\LuckyNumbersService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/lucky', name: 'lucky_')]
class LuckyNumbersController extends AbstractController
{
    public function __construct(
        private readonly LuckyNumbersService $svc,
        private readonly Connection $db
    ) {}

    /**
     * GET /api/lucky/{game}/draw?seed=&save=1
     * For game=custom you may add: pool, picks, bonus_pool, bonus_picks
     */
    #[Route('/{game}/draw', name: 'draw', methods: ['GET'])]
    public function draw(string $game, Request $req): JsonResponse
    {
        $seed = $req->query->get('seed');
        $save = (bool) $req->query->get('save', false);

        $pool       = $req->query->has('pool')        ? $req->query->getInt('pool')        : null;
        $picks      = $req->query->has('picks')       ? $req->query->getInt('picks')       : null;
        $bonusPool  = $req->query->has('bonus_pool')  ? $req->query->getInt('bonus_pool')  : null;
        $bonusPicks = $req->query->has('bonus_picks') ? $req->query->getInt('bonus_picks') : null;

        $draw = $this->svc->draw($game, $pool, $picks, $bonusPool, $bonusPicks, $seed);

        if ($save) {
            $this->db->insert('repweb_lucky_numbers', [
                'game'         => $draw['game'],
                'numbers_json' => json_encode($draw['numbers'], JSON_UNESCAPED_SLASHES),
                'bonus_json'   => $draw['bonus'] ? json_encode($draw['bonus'], JSON_UNESCAPED_SLASHES) : null,
                'seed'         => $draw['seed'],
                'requester'    => $req->getClientIp(),
                'note'         => $req->query->get('note'),
            ]);
        }

        return $this->json($draw);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $req): JsonResponse
    {
        $game  = $req->query->get('game');
        $limit = min(100, max(1, $req->query->getInt('limit', 20)));

        $sql = 'SELECT id, created_at, game, numbers_json, bonus_json, seed, requester, note
                FROM repweb_lucky_numbers';
        if (!empty($game)) {
            $sql .= ' WHERE game = :g';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :lim';

        $stmt = $this->db->prepare($sql);
        if (!empty($game)) {
            $stmt->bindValue('g', $game);
        }
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);

        $rows = $stmt->executeQuery()->fetchAllAssociative();

        $rows = array_map(static function (array $r): array {
            return [
                'id'         => (int) $r['id'],
                'created_at' => $r['created_at'],
                'game'       => $r['game'],
                'numbers'    => json_decode($r['numbers_json'], true),
                'bonus'      => $r['bonus_json'] ? json_decode($r['bonus_json'], true) : null,
                'seed'       => $r['seed'],
                'requester'  => $r['requester'],
                'note'       => $r['note'],
            ];
        }, $rows);

        return $this->json(['items' => $rows]);
    }
}
