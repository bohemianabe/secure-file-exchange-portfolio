<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\BlockedIpsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlockedIpsRepository::class)]
#[ORM\Index(name: 'idx_blocked_until', columns: ['blocked_until'])]
class BlockedIps
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_blocked_ip', type: 'integer', nullable: false)]
    private ?int $idBlockedIp = null;

    #[ORM\Column(name: 'ip_address', type: 'string', length: 45, unique: true)]
    private string $ipAddress;

    #[ORM\Column(name: 'reason', type: 'string', length: 100)]
    private string $reason;

    #[ORM\Column(name: 'trigger_type', type: 'string', length: 64)]
    private string $triggerType;

    #[ORM\Column(name: 'blocked_until', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $blockedUntil;

    public function isActive(): bool
    {
        return $this->blockedUntil > new \DateTimeImmutable();
    }
    public function getId(): int
    {
        return $this->idBlockedIp;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    public function setTriggerType(string $triggerType): self
    {
        $this->triggerType = $triggerType;
        return $this;
    }

    public function getBlockedUntil(): \DateTimeImmutable
    {
        return $this->blockedUntil;
    }

    public function setBlockedUntil(\DateTimeImmutable $blockedUntil): self
    {
        $this->blockedUntil = $blockedUntil;
        return $this;
    }
}
