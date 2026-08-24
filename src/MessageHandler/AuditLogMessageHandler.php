<?php

namespace App\MessageHandler;

use App\Entity\AuditLogs;
use App\Message\AuditLogMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AuditLogMessageHandler
{
    public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger) {}

    public function __invoke(AuditLogMessage $m): void
    {
        $auditLog = (new AuditLogs())
            ->setOccurredAt($m->occurredAt)
            ->setAction($m->action)
            ->setResourceType($m->resourceType)
            ->setResourceId($m->resourceId)
            ->setSuccess($m->success)
            // ->setMetadata($m->metadata ?: null)
            ->setSeverity($m->severity)
            ->setUserId($m->userId)
            ->setUserEmail($m->userEmail)
            ->setMethod($m->method)
            ->setPath($m->path)
            ->setIp($m->ip)
            ->setRoute($m->route)
            ->setSessionId($m->sessionId)
            ->setRequestId($m->requestId);

        try {
            $metadata = $this->normalizeMetadata($m->metadata);

            $auditLog->setMetadata($metadata);
            $this->em->persist($auditLog);
            $this->em->flush();
        } catch (\Throwable $e) {
            // ag: PHP error_logs outputs to what your settings are in the .ini file. Typically: /var/log/apache2/error.log or /var/log/nginx/error.log. Here I'm using monolog
            $this->logger->error(print_r([
                'audit_log_failure' => true,
                'message_class' => $m::class,
                'action' => $m->action ?? null,
                'resource_type' => $m->resourceType ?? null,
                'resource_id' => $m->resourceId ?? null,
                'metadata_type' => isset($metadata) ? get_debug_type($metadata) : null,
                'exception' => $e->getMessage(),
            ], true));

            throw $e;
        }
    }

    private function normalizeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (!is_array($metadata)) {
            throw new \RuntimeException(sprintf(
                'Audit metadata must be array|null, got %s',
                get_debug_type($metadata)
            ));
        }

        json_encode($metadata, JSON_THROW_ON_ERROR);

        return $metadata;
    }
}
