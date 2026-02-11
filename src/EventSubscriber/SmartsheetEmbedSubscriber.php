<?php

namespace App\EventSubscriber;

use App\Security\EmbedTokenService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SmartsheetEmbedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EmbedTokenService $tokenService,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/smartsheet')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() && $token->getUser() !== 'anon.') {
            return;
        }

        $token = (string) $request->cookies->get(EmbedTokenService::COOKIE_NAME, '');
        if ($token === '') {
            $token = (string) $request->headers->get('X-Embed-Token', '');
        }

        $payload = $token !== ''
            ? $this->tokenService->validateToken(
                $token,
                EmbedTokenService::AUDIENCE_RAMSEIER,
                EmbedTokenService::SCOPE_SMARTSHEET
            )
            : null;

        if (!$payload) {
            $event->setResponse(new JsonResponse([
                'message' => 'Embed token required.',
            ], Response::HTTP_UNAUTHORIZED));
        }
    }
}
