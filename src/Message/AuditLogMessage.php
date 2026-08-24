<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_default')]
final class AuditLogMessage
{
    public function __construct(
        public readonly string $action,
        public readonly ?string $resourceType,
        public readonly ?string $resourceId,
        public readonly bool $success,
        public readonly array $metadata,
        public readonly ?int $userId,
        public readonly ?string $userEmail,
        public readonly ?string $ip,
        public readonly ?string $route,
        public readonly ?string $sessionId,
        public readonly ?string $requestId,
        public readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
        // ag: severity types: info/warning/error
        public readonly string $severity = 'info',
        public readonly ?string $method = null,
        public readonly ?string $path = null,
    ) {}
}
