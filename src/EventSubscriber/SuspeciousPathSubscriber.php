<?php

namespace App\EventSubscriber;

use App\Entity\SecurityEvents;
use App\Service\IpAbuseProtectionService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SuspeciousPathSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private readonly IpAbuseProtectionService $ipAbuseProtectionService,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($this->ipAbuseProtectionService->isAllowedPath($path)) {
            return;
        }

        $riskLevel = $this->ipAbuseProtectionService->matchSuspiciousPath($path);

        if ($riskLevel === null) {
            return;
        }

        $eventType = $riskLevel === 'high'
            ? SecurityEvents::EVENT_SUSPICIOUS_PATH_HIGH_RISK
            : SecurityEvents::EVENT_SUSPICIOUS_PATH;

        $this->ipAbuseProtectionService->recordEvent(
            eventType: $eventType,
            ip: $request->getClientIp(),
            route: $request->attributes->get('_route'),
            userAgent: $request->headers->get('User-Agent'),
            metadata: [
                'path' => $path,
                'method' => $request->getMethod(),
                'risk' => $riskLevel,
            ]
        );

        $this->ipAbuseProtectionService->evaluateSuspiciousPathThresholds(
            $request->getClientIp()
        );
    }

    public static function getSubscribedEvents(): array
    {
        // 150 indicated the priority this event will run the high the number the more priority it will have. This runs second to BlockedIpSubscriber
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 150],
        ];
    }
}
