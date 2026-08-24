<?php

namespace App\Logging;

use App\Message\AuditLogMessage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;

final class AuditLogger
{
    public function __construct(
        private MessageBusInterface $bus,
        private Security $security,
        private RequestStack $requests,
    ) {}

    public function log(string $action, ?string $resourceType = null, ?string $resourceId = null, bool $success = true, array $metadata = [], string $severity = 'info'): void
    {
        $req = $this->requests->getCurrentRequest();
        $user = $this->security->getUser();

        // ag: Safety check: added this if check for sessionId incase audiLogger is ran in an area that doesn't have a live session
        $sessionId = null;

        if ($req && $req->hasSession()) {
            $sessionId = $req->getSession()->getId();
        }

        $this->bus->dispatch(new AuditLogMessage(
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            success: $success,
            metadata: $metadata,
            userId: \is_object($user) && method_exists($user, 'getId') ? (int) $user->getId() : null,
            userEmail: \is_object($user) && method_exists($user, 'getEmail') ? (string) $user->getEmail() : null,
            ip: $req?->getClientIp(),
            method: $req?->getMethod(),
            path: $req?->getPathInfo(),
            route: $req?->attributes->get('_route'),
            sessionId: $sessionId,
            requestId: $req?->attributes->get('request_id'),
            severity: $severity,
        ));
    }
}
