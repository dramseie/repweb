<?php

namespace App\Controller;

use App\Security\EmbedTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SmartsheetEmbedController extends AbstractController
{
    #[Route('/smartsheet/embed', name: 'smartsheet_embed', methods: ['GET'])]
    public function __invoke(Request $request, EmbedTokenService $tokenService): Response
    {
        $queryToken = (string) $request->query->get('token', '');
        if ($queryToken !== '') {
            $payload = $tokenService->validateToken(
                $queryToken,
                EmbedTokenService::AUDIENCE_RAMSEIER,
                EmbedTokenService::SCOPE_SMARTSHEET
            );

            if (!$payload) {
                $response = new Response('Invalid embed token.', Response::HTTP_UNAUTHORIZED);
                return $this->applyEmbedHeaders($response);
            }

            $cookie = Cookie::create(EmbedTokenService::COOKIE_NAME)
                ->withValue($queryToken)
                ->withExpires((int) ($payload['exp'] ?? time()))
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('none');

            $response = new RedirectResponse('/smartsheet/embed');
            $response->headers->setCookie($cookie);

            return $this->applyEmbedHeaders($response);
        }

        $cookieToken = (string) $request->cookies->get(EmbedTokenService::COOKIE_NAME, '');
        if ($cookieToken === '') {
            $response = new Response('Embed token required.', Response::HTTP_UNAUTHORIZED);
            return $this->applyEmbedHeaders($response);
        }

        $payload = $tokenService->validateToken(
            $cookieToken,
            EmbedTokenService::AUDIENCE_RAMSEIER,
            EmbedTokenService::SCOPE_SMARTSHEET
        );

        if (!$payload) {
            $response = new Response('Embed token expired.', Response::HTTP_UNAUTHORIZED);
            return $this->applyEmbedHeaders($response);
        }

        $response = $this->render('smartsheet/embed.html.twig');

        return $this->applyEmbedHeaders($response);
    }

    private function applyEmbedHeaders(Response $response): Response
    {
        $response->headers->set('Content-Security-Policy', 'frame-ancestors https://www.ramseier.com');
        $response->headers->set('X-Frame-Options', 'ALLOW-FROM https://www.ramseier.com');

        return $response;
    }
}
