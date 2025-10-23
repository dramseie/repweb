<?php

namespace App\Controller;

use App\Security\RepwebJwtSigner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Cookie;

final class GrafanaAuthController
{
    public function __construct(private RepwebJwtSigner $signer) {}

    #[Route('/grafana/jwt/test', name: 'grafana_jwt_test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        $claims = [
            'sub'   => 'repweb-test-user',
            'email' => 'test@repweb.local',
            'aud'   => 'grafana',
            'iss'   => 'repweb',
        ];
        $jwt = $this->signer->mint($claims, 300); // 5 minutes
        return new JsonResponse(['token' => $jwt, 'claims' => $claims]);
    }

    #[Route('/grafana/sso', name: 'grafana_sso', methods: ['GET'])]
    public function sso(Request $request): Response
    {
        // TODO: pull real Symfony user
        $claims = [
            'sub'   => 'repweb-user',          // must match Grafana username_claim
            'email' => 'user@example.com',     // must match Grafana email_claim
            'aud'   => 'grafana',
            'iss'   => 'repweb',
        ];
        $ttl = 300;
        $jwt = $this->signer->mint($claims, $ttl);

        // IMPORTANT for iframe on :3001 -> SameSite=None; Secure
        $cookie = Cookie::create(
            'repweb_jwt',
            $jwt,
            time() + $ttl,
            '/',           // path (you can set '/'' or '/grafana' depending on your Apache rules)
            null,          // domain (null => current host repweb.ramseier.com)
            true,          // secure
            true,          // httpOnly
            false,         // raw
            Cookie::SAMESITE_NONE
        );

        // Allow optional ?next=... (defaults to the Grafana proxy on 3001)
        $next = $request->query->get('next', 'https://repweb.ramseier.com:3001/');

        $resp = new RedirectResponse($next);
        $resp->headers->set('Cache-Control', 'no-store');
        $resp->headers->setCookie($cookie);
        return $resp;
    }

    #[Route('/grafana/jwt/clear', name: 'grafana_jwt_clear', methods: ['POST','GET'])]
    public function clear(): Response
    {
        // Expire cookie
        $cookie = Cookie::create(
            'repweb_jwt',
            '',
            time() - 3600,
            '/',
            null,
            true,
            true,
            false,
            Cookie::SAMESITE_NONE
        );

        $resp = new RedirectResponse('/');
        $resp->headers->set('Cache-Control', 'no-store');
        $resp->headers->setCookie($cookie);
        return $resp;
    }
}
