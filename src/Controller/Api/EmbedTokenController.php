<?php

namespace App\Controller\Api;

use App\Security\EmbedTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmbedTokenController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(EMBED_API_KEY)%')]
        private readonly string $apiKey
    ) {
    }

    #[Route('/api/embed/token', name: 'api_embed_token', methods: ['POST'])]
    public function mint(Request $request, EmbedTokenService $tokenService): JsonResponse
    {
        $provided = (string) $request->headers->get('X-Embed-Key', '');
        if ($this->apiKey === '' || $provided === '' || !hash_equals($this->apiKey, $provided)) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $expiresIn = 900;
        try {
            $payload = $request->toArray();
            if (isset($payload['expiresIn']) && is_numeric($payload['expiresIn'])) {
                $expiresIn = (int) $payload['expiresIn'];
            }
        } catch (\JsonException) {
            $expiresIn = 900;
        }

        $tokenData = $tokenService->createToken(
            EmbedTokenService::AUDIENCE_RAMSEIER,
            EmbedTokenService::SCOPE_SMARTSHEET,
            $expiresIn
        );

        return new JsonResponse([
            'token' => $tokenData['token'],
            'expiresAt' => $tokenData['expiresAt'],
        ]);
    }
}
