<?php
// src/Controller/Api/PosTimerController.php
namespace App\Controller\Api;

use App\Service\PosTimerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/pos/orders/{orderId}/timer', name: 'api_pos_timer_')]
final class PosTimerController extends AbstractController
{
    public function __construct(private PosTimerManager $timers) {}

    #[Route('/start', methods: ['POST'], name: 'start')]
    public function start(int $orderId): JsonResponse { $this->timers->startForOrder($orderId); return $this->json(['ok'=>true]); }

    #[Route('/pause', methods: ['POST'], name: 'pause')]
    public function pause(int $orderId): JsonResponse { $this->timers->pauseForOrder($orderId); return $this->json(['ok'=>true]); }

    #[Route('/finish', methods: ['POST'], name: 'finish')]
    public function finish(int $orderId): JsonResponse { $this->timers->finishForOrder($orderId); return $this->json(['ok'=>true]); }

    #[Route('/status', methods: ['GET'], name: 'status')]
    public function status(int $orderId): JsonResponse { return $this->json($this->timers->getStatusForOrder($orderId)); }

    #[Route('/finalize', methods: ['POST'], name: 'finalize')]
    public function finalize(int $orderId): JsonResponse { return $this->json($this->timers->finalizeForOrder($orderId)); }
}
