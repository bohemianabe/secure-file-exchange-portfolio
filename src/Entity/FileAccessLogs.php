<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Enum\FileAccessLogAction;
use App\Repository\FileAccessLogsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileAccessLogsRepository::class)]
class FileAccessLogs
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_file_access_log', type: 'integer', nullable: false)]
    private ?int $idAccessLog = null;

    #[ORM\ManyToOne(targetEntity: Files::class)]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id_file', nullable: true, onDelete: 'SET NULL')]
    private ?Files $file = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    // ag: incase user is deleted I'll have the email for record keeping
    #[ORM\Column(name: 'user_email', length: 180, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(name: 'action', length: 50, enumType: FileAccessLogAction::class)]
    private ?FileAccessLogAction $action = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    public function getId(): ?int
    {
        return $this->idAccessLog;
    }

    public function getFile(): Files
    {
        return $this->file;
    }

    public function setFile(Files $file): self
    {
        $this->file = $file;
        return $this;
    }
    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): self
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    public function getAction(): ?FileAccessLogAction
    {
        return $this->action;
    }

    public function setAction(?FileAccessLogAction $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }
}
