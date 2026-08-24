<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\SoftDeletableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\UuidTrait;
use App\Enum\FileType;
use App\Repository\FilesRepository;
use App\Utils\FileSizeFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FilesRepository::class)]
#[ORM\Index(columns: ['firm_id'], name: 'idx_files_firm')]
#[ORM\Index(columns: ['client_id'], name: 'idx_files_client')]
#[ORM\Index(columns: ['created_at'], name: 'idx_files_created_date')]
class Files
{
    public const FIRM_ALL_CLIENTS = 'firm_all_clients';
    public const SINGLE_CLIENT = 'single_client';

    use SoftDeletableTrait;
    use UuidTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_file', type: 'integer', nullable: false)]
    private ?int $idFile = null;

    // ag: this column will track file versioning
    #[ORM\Column(name: 'parent_file_id', type: 'string', nullable: true)]
    private ?string $parentFileId = null;

    #[ORM\Column(name: 'version', nullable: true, type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'name', type: 'string', length: 128, nullable: true)]
    private ?string $name = null;

    // REQUIRED — deleting a Firm deletes its files
    #[ORM\ManyToOne(targetEntity: Firms::class, inversedBy: 'files')]
    #[ORM\JoinColumn(name: 'firm_id', referencedColumnName: 'id_firm', nullable: false, onDelete: 'CASCADE')]
    private Firms $firm;

    // OPTIONAL — if set, deleting the Client deletes those files
    #[ORM\ManyToOne(targetEntity: Clients::class, inversedBy: 'files')]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id_client', nullable: true, onDelete: 'CASCADE')]
    private ?Clients $client = null;

    // keep files if firm user is deleted
    #[ORM\ManyToOne(targetEntity: FirmUserProfiles::class)]
    #[ORM\JoinColumn(name: 'firm_user_profile_id', referencedColumnName: 'id_firm_user_profile', nullable: true, onDelete: 'SET NULL')]
    private ?FirmUserProfiles $uploadedByFirmUser = null;

    // ag: not sure if I need this, but will keep. Files should be traced to the Clients entity and all CUP will have access
    #[ORM\ManyToOne(targetEntity: ClientUserProfiles::class)]
    #[ORM\JoinColumn(name: 'client_user_profile_id', referencedColumnName: 'id_client_user_profile', nullable: true, onDelete: 'SET NULL')]
    private ?ClientUserProfiles $uploadedByClientUser = null;

    #[ORM\Column(name: 'original_name', type: 'string', length: 255)]
    private string $originalName;

    #[ORM\Column(name: 'storage_path', type: 'string', length: 255)]
    private string $storagePath; // e.g. uploads/firm_{id}/yyyy/mm/uuid.ext

    #[ORM\Column(name: 'mime_type', length: 191, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(name: 'size_bytes', type: 'bigint')]
    private int $sizeBytes;

    #[ORM\Column(name: 'checksum_sha256', length: 64, nullable: true)]
    private ?string $checksumSha256 = null;

    #[ORM\Column(name: 'notes', length: 252, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', name: 'visibility')]
    private string $visibility = self::SINGLE_CLIENT;

    #[ORM\OneToMany(mappedBy: 'file', targetEntity: SignatureRequests::class, cascade: ['remove'])]
    private Collection $signatureRequests;

    #[ORM\ManyToOne(inversedBy: 'files', targetEntity: Folders::class)]
    #[ORM\JoinColumn(name: 'folder_id', referencedColumnName: "id_folder", nullable: true)]
    private ?Folders $folder = null;

    #[ORM\ManyToOne(inversedBy: 'files', targetEntity: FileRequests::class)]
    #[ORM\JoinColumn(name: 'file_request_id', referencedColumnName: "id_file_request", nullable: true)]
    private ?FileRequests $fileRequest = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expirationDate = null;

    // ag: the date the file was last accessed.
    #[ORM\Column(name: 'last_accessed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAccessedAt = null;

    #[ORM\Column(name: 'download_count', type: 'integer', nullable: true)]
    private ?int $downloadCount = null;

    public function __construct()
    {
        $this->signatureRequests = new ArrayCollection();
        // ag: use ->toRfc4122() so it returns a string instead of the uuid object
        $this->uuid = Uuid::v7()->toRfc4122();
    }

    public function hasSignatureRequests(): bool
    {
        return $this->signatureRequests instanceof Collection && !$this->signatureRequests->isEmpty();
    }

    public function getSignatureRequests(): Collection
    {
        return $this->signatureRequests;
    }

    public function getFileType(): FileType
    {
        $extension = pathinfo(
            $this->getOriginalName(),
            PATHINFO_EXTENSION
        );

        return FileType::fromExtension($extension);
    }

    public function getFileExtention(): string
    {
        return strtolower(pathinfo($this->getOriginalName(), PATHINFO_EXTENSION));
    }

    public function getFileIcon(): string
    {
        return $this->getFileType()->icon();
    }

    public function getFileIconBgClass(): string
    {
        return $this->getFileType()->getBackgroundClass();
    }

    public function isFileRequest(): bool
    {
        if ($this->fileRequest == null) {
            return false;
        } else {
            return true;
        }
    }

    public function getFileRequest(): ?FileRequests
    {
        return $this->fileRequest;
    }

    public function setFileRequest(?FileRequests $fileRequest): self
    {
        $this->fileRequest = $fileRequest;
        return $this;
    }

    public function hasFolder(): bool
    {
        if ($this->folder == null) {
            return false;
        } else {
            return true;
        }
    }

    public function getFolder(): ?Folders
    {
        return $this->folder;
    }

    public function setFolder(?Folders $folder = null): self
    {
        $this->folder = $folder;
        return $this;
    }

    // public function removeAssignedFolder(Folders $folder): self
    // {
    //     $this->folder = null;
    //     return $this;
    // }


    // public function getSignatureRequests(): Collection
    // {
    //     return $this->signatureRequests;
    // }

    public function setDefaultExpirationDate(): void
    {
        if ($this->expirationDate === null && $this->firm) {
            $days = $this->firm->getFileDurationDays() ?? 60;
            $this->expirationDate = (new \DateTimeImmutable())->modify("+{$days} days");
        }
    }

    // -------- getters/setters (only the important ones shown) --------

    public function getId(): ?int
    {
        return $this->idFile;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getParentFileId(): string
    {
        return $this->parentFileId;
    }

    public function setParentFileId(?string $parentFileId): self
    {
        $this->parentFileId = $parentFileId;
        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getFirm(): Firms
    {
        return $this->firm;
    }

    public function setFirm(Firms $firm): self
    {
        $this->firm = $firm;
        return $this;
    }

    public function getClient(): ?Clients
    {
        return $this->client;
    }

    public function setClient(?Clients $client): self
    {
        $this->client = $client;
        if (!is_null($client)) {
            $this->visibility = self::SINGLE_CLIENT;
        } else {
            $this->visibility = self::FIRM_ALL_CLIENTS;
        }
        return $this;
    }

    public function getUploadedByFirmUser(): ?FirmUserProfiles
    {
        return $this->uploadedByFirmUser;
    }

    public function setUploadedByFirmUser(?FirmUserProfiles $u): self
    {
        $this->uploadedByFirmUser = $u;
        return $this;
    }

    public function getUploadedByClientUser(): ?ClientUserProfiles
    {
        return $this->uploadedByClientUser;
    }

    public function setUploadedByClientUser(?ClientUserProfiles $u): self
    {
        $this->uploadedByClientUser = $u;
        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }
    public function setOriginalName(string $n): self
    {
        $this->originalName = $n;
        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $p): self
    {
        $this->storagePath = $p;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }
    public function setMimeType(?string $m): self
    {
        $this->mimeType = $m;
        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $s): self
    {
        $this->sizeBytes = $s;
        return $this;
    }

    public function getSizeHuman(): string
    {

        return $this->sizeBytes ? FileSizeFormatter::format($this->sizeBytes) : 'n/a';
    }

    public function getChecksumSha256(): ?string
    {
        return $this->checksumSha256;
    }

    public function setChecksumSha256(?string $c): self
    {
        $this->checksumSha256 = $c;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    // public function getIsRequestedFile(): ?bool
    // {
    //     return $this->isRequestedFile;
    // }

    // public function setIsRequestedFile(int $status): self
    // {
    //     $this->isRequestedFile = $status;
    //     return $this;
    // }

    public function getExpirationDate(): ?\DateTimeImmutable
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeImmutable $expirationDate): self
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function getLastAccessedAt(): ?\DateTimeImmutable
    {
        return $this->lastAccessedAt;
    }

    public function setLastAccessedAt(\DateTimeImmutable $lastAccessedAt): self
    {
        $this->lastAccessedAt = $lastAccessedAt;
        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $v): self
    {
        $this->visibility = $v;
        if ($v === self::FIRM_ALL_CLIENTS) {
            $this->client = null;
        }
        return $this;
    }

    public function incrementDownloadCount(): self
    {
        $this->downloadCount = ($this->downloadCount ?? 0) + 1;
        return $this;
    }
}
