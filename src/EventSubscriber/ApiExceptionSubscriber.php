<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $req = $event->getRequest();
        if (0 !== strpos($req->getPathInfo(), '/api/')) {
            return; // only for API paths
        }

        $e = $event->getThrowable();
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $payload = [
            'error' => $e->getMessage(),
            'type'  => (new \ReflectionClass($e))->getShortName(),
        ];

        // In dev, include a hint stack for faster debugging (trimmed)
        if ($_ENV['APP_ENV'] ?? 'prod' !== 'prod') {
            $payload['hint'] = substr($e->getFile().':'.$e->getLine(), 0, 500);
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }
}
