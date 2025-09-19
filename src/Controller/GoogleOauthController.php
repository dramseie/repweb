<?php
namespace App\Controller;

use App\Service\GoogleCalendarService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class GoogleOauthController extends AbstractController   // 👈 match filename
{
    public function __construct(
        private GoogleCalendarService $gcal,
        private Connection $db,
    ) {}

    #[Route('/api/google/oauth/connect', name: 'google_oauth_connect', methods: ['GET'])]
    public function connect(): Response
    {
        $userId  = 1; // later: replace with logged-in user id
        $authUrl = $this->gcal->buildAuthUrl($userId);
        return $this->redirect($authUrl);
    }

    #[Route('/api/google/oauth/callback', name: 'google_oauth_callback', methods: ['GET'])]
    public function callback(Request $req): Response
    {
        $code  = (string) $req->query->get('code', '');
        $state = $req->query->get('state');

        if ($code === '') {
            return $this->json(['error' => 'Missing OAuth code'], 400);
        }

        $userId = 1;
        if (is_string($state) && ctype_digit($state)) {
            $userId = (int) $state;
        }

        try {
            $this->gcal->exchangeCodeAndStore($code, $userId);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }

        return $this->redirect('/pos/agenda?google=connected');
    }
}
