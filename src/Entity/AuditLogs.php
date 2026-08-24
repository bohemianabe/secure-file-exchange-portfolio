<?php

namespace App\Entity;

use App\Repository\AuditLogsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogsRepository::class)]
#[ORM\Index(columns: ['occurred_at'])]
#[ORM\Index(columns: ['action', 'resource_type'])]
#[ORM\Index(columns: ['user_id', 'session_id'])]
#[ORM\Table(name: 'audit_logs')]
class AuditLogs
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_audit_log', nullable: false, type: 'bigint')]
    private ?int $idAuditLog = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    // Who did it (nullable for guests/automations)
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: "user_email", type: "string", length: 180, nullable: true)]
    private ?string $userEmail = null;

    // What happened
    #[ORM\Column(length: 64)] // e.g. 'login', 'file.upload', 'firm.update'
    private string $action;

    // On which resource 
    #[ORM\Column(length: 64, nullable: true)] // e.g. 'File','Firm','User'
    private ?string $resourceType = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)] // FK as string to support UUIDs
    private ?string $resourceId = null;

    // Request context
    #[ORM\Column(length: 62, nullable: true)]  // IPv4/6 → if you need both, use length:45
    private ?string $ip = null;

    #[ORM\Column(length: 16, nullable: true)]  // GET/POST/etc
    private ?string $method = null;

    #[ORM\Column(length: 255, nullable: true)] // /admin/firm-view/123
    private ?string $path = null;

    #[ORM\Column(length: 120, nullable: true)] // route name
    private ?string $route = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $requestId = null;       // generate per-request UUID

    // Outcome & meta
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $success = true;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'info'])]
    private string $severity = 'info';       // info|warn|error|security

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;         // arbitrary SAFE details (filenames, sizes, etc)

    // …getters/setters…
    public function getId(): ?int
    {
        return $this->idAuditLog;
    }

    public function getOccurredAt(): ?\DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(?\DateTimeInterface $occuredAt): self
    {
        $this->occurredAt = $occuredAt;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $id): self
    {
        $this->userId = $id;
        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): self
    {
        $this->userEmail = $userEmail !== null
            ? mb_strtolower($userEmail)
            : null;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    public function setResourceType(?string $resourceType): self
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function setResourceId(?string $resourceId): self
    {
        $this->resourceId = $resourceId;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    public function getSuccess(): ?bool
    {
        return $this->success;
    }

    public function setSuccess(?bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(?string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}
