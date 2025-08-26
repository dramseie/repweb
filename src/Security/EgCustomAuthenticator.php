<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class EgCustomAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private UserProviderInterface $userProvider,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function supports(Request $request): ?bool
    {
        // Adjust if your login path changes
        return $request->isMethod('POST') && $request->getPathInfo() === '/login';
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // If user originally requested a protected page, send them back there
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Fallback to an existing route ('home' exists in your router list; use 'admin' if you prefer)
        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
