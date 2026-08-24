<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\UuidTrait;
use App\Repository\FilesExportRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FilesExportRequestRepository::class)]
class FilesExportRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    use UuidTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_file_export_request', type: 'integer', nullable: false)]
    private ?int $idFileExportRequest = null;

    // convenience denormalization if you want fast scoping; optional
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(name: 'status', length: 64, type: 'string', nullable: false)]
    private string $status; // pending, processing, complete, failed

    #[ORM\Column(name: 'zip_path', type: 'string', nullable: true)]
    private ?string $zipPath = null; // completed file path

    #[ORM\Column(name: 'zip_downloaded', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $zipDownloaded = false;

    // ag: keep track if zip needs to be removed if client never downloads it
    #[ORM\Column(name: 'zip_removed', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $zipRemoved = false;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;


    public function __construct()
    {
        $this->uuid = Uuid::v7()->toRfc4122();
    }

    // getters/setters…
    public function getId(): ?int
    {
        return $this->idFileExportRequest;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getZipPath()
    {
        return $this->zipPath;
    }

    public function setZipPath(?string $zipPath): self
    {
        $this->zipPath = $zipPath;
        return $this;
    }

    public function getZipDownloaded(): bool
    {
        return $this->zipDownloaded;
    }

    public function setZipDownloaded(?bool $zipDownloaded): self
    {
        $this->zipDownloaded = $zipDownloaded;
        return $this;
    }

    public function getZipRemoved(): bool
    {
        return $this->zipRemoved;
    }

    public function setZipRemoved(?bool $zipRemoved): self
    {
        $this->zipRemoved = $zipRemoved;
        return $this;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }
}
