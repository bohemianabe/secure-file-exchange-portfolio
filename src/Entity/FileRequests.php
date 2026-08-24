<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\FileRequestsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: FileRequestsRepository::class)]
class FileRequests
{
    public const STATUS_PENDING = 'Pending';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELED = 'Cancelled';
    public const STATUS_EXPIRED = 'Invitation Expired';

    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idFileRequest = null;

    #[ORM\ManyToOne(inversedBy: 'fileRequests', targetEntity: Firms::class)]
    #[ORM\JoinColumn(name: 'firm_id', referencedColumnName: 'id_firm', nullable: false)]
    private Firms $firm;

    #[ORM\ManyToOne(inversedBy: 'fileRequests', targetEntity: FirmUserProfiles::class)]
    #[ORM\JoinColumn(name: 'firm_user_profile_id', referencedColumnName: 'id_firm_user_profile', nullable: false)]
    private FirmUserProfiles $firmUserProfile;

    #[ORM\Column(name: 'title', type: 'string', length: 128, nullable: false)]
    private string $title;

    #[ORM\Column(name: 'recipient_name', type: 'string', length: 128, nullable: false)]
    private string $recipientName;

    #[ORM\Column(name: "email", type: "string", length: 180, nullable: false)]
    private ?string $email = null;

    // #[ORM\Column(name: 'type', type: 'string', length: 128, nullable: false)]
    // private string $type; 

    #[ORM\Column(name: 'status', type: 'string', length: 50)]
    private ?string $status = self::STATUS_PENDING; // pending, signed, declined, cancelled

    #[ORM\Column(name: 'hashed_token', type: 'string', length: 255, nullable: false)]
    private ?string $hashedToken = null;

    #[ORM\Column(name: 'is_passcode', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $isPasscode = false;

    #[ORM\Column(name: 'hashed_passcode', type: 'string', length: 255, nullable: true)]
    private ?string $hashedPasscode = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $tokenExpiresAt = null;

    #[ORM\OneToMany(mappedBy: 'fileRequest', targetEntity: Files::class)]
    private Collection $files;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function hasFiles(): bool
    {
        return $this->files instanceof Collection && !$this->files->isEmpty();
    }

    public function getFiles(): Collection
    {
        // return new ArrayCollection($this->hasFiles() ? $this->files->toArray() : []);

        return $this->files;
    }

    public function isValidRequest(): bool
    {
        return $this->tokenExpiresAt !== null
            && $this->status == self::STATUS_PENDING
            && $this->tokenExpiresAt > new \DateTimeImmutable();
    }

    public function hasPasscode(): bool
    {
        if ($this->hashedPasscode == null) {
            return false;
        } else {
            return true;
        }
    }
    public function getId(): ?int
    {
        return $this->idFileRequest;
    }

    public function getFirm(): ?Firms
    {
        return $this->firm;
    }

    public function setFirm(Firms $firm): self
    {
        $this->firm = $firm;
        return $this;
    }

    public function getFirmUserProfile(): ?FirmUserProfiles
    {
        return $this->firmUserProfile;
    }

    public function setFirmUserProfile(FirmUserProfiles $fup): self
    {
        $this->firmUserProfile = $fup;
        return $this;
    }

    // public function getFile(): ?Files
    // {
    //     return $this->file;
    // }

    // public function setFile(Files $file): self
    // {
    //     $this->files = $file;
    //     return $this;
    // }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): self
    {
        $this->recipientName = $recipientName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    // public function getType(): ?string
    // {
    //     return $this->type;
    // }

    // public function setType(?string $type): self
    // {
    //     $this->type = $type;
    //     return $this;
    // }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }
    public function getHashedToken(): string
    {
        return $this->hashedToken;
    }

    public function setHashedToken(string $hashedToken): self
    {
        $this->hashedToken = $hashedToken;

        return $this;
    }

    public function getIsPasscode(): ?bool
    {
        return $this->isPasscode;
    }

    public function setIsPasscode(bool $status): self
    {
        $this->isPasscode = $status;

        return $this;
    }

    public function getHashedPasscode(): string
    {
        return $this->hashedPasscode;
    }

    public function setHashedPasscode(?string $hashedPasscode): self
    {
        $this->hashedPasscode = $hashedPasscode;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->tokenExpiresAt = $expiresAt;
        return $this;
    }

    // public function isTokenValid(string $token): bool
    // {
    //     return $this->token === $token &&
    //         $this->tokenExpiresAt &&
    //         $this->tokenExpiresAt > new \DateTimeImmutable();
    // }

}
