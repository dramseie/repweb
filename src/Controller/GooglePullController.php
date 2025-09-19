<?php
namespace App\Controller;

use App\Service\GooglePullSync;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class GooglePullController extends AbstractController
{
    public function __construct(private GooglePullSync $pull) {}

    #[Route('/api/google/sync/pull', name: 'google_sync_pull', methods: ['POST','GET'])]
    public function pull(): JsonResponse
    {
        $userId = 1; // TODO: real user id
        $stats = $this->pull->syncUserFromGoogle($userId, 'primary');
        return $this->json(['ok' => true, 'stats' => $stats]);
    }
}
