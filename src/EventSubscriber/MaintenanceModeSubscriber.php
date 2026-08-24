<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

// implements EventSubscriberInterface declares its own subscribed events in the class itself. While a listen will: tied to one event registration externally registered in config, attributes, or tags Symfony is told: “call this method for this event”
class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly bool $maintenanceMode,
        private readonly string $appName,
    ) {}

    // ag: makes it different from a plain EventListener
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $allowedIps = ['127.0.0.1', '::1', '172.21.0.1'];

        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->maintenanceMode) {
            return;
        }

        if (in_array($event->getRequest()->getClientIp(), $allowedIps, true)) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Let assets and a few internal paths through
        if (
            str_starts_with($path, '/build') ||
            str_starts_with($path, '/assets') ||
            str_starts_with($path, '/images') ||
            str_starts_with($path, '/favicon.ico') ||
            str_starts_with($path, '/_wdt') ||
            str_starts_with($path, '/_profiler')
        ) {
            return;
        }

        $html = $this->twig->render('maintenance/index.html.twig', ['message' => 'Site under maintenance. We\'re sorry for the inconvenience, as we\'re hard at work improving our services. Please check in later.', 'app_name' => $this->appName]);

        $response = new Response($html, Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Retry-After', '3600');

        $event->setResponse($response);
    }
}
