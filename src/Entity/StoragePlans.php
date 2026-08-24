<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\UuidTrait;
use App\Repository\StoragePlansRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StoragePlansRepository::class)]
class StoragePlans
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_storage_plan', nullable: false, type: 'integer')]
    private ?int $idStoragePlan = null;

    #[ORM\Column(name: 'name', type: 'string', length: 64, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(name: 'storage', type: 'bigint', options: ['unsigned' => true])]
    private ?string $storage = null;

    #[ORM\Column(name: 'description', type: 'string', length: 252, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'max_file_size', type: 'bigint', options: ['unsigned' => true])]
    private ?string $maxFileSize = null;

    #[ORM\Column(name: 'max_users', type: 'integer', nullable: true, options: ['default' => 2])]
    private ?int $maxUsers;

    #[ORM\Column(name: 'price', type: Types::DECIMAL, precision: 8, scale: 2, nullable: false)]
    private ?string $price = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', nullable: true, options: ['default' => true])]
    private ?bool $isActive = false;

    #[ORM\Column(name: 'is_custom_plan', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $isCustomPlan = false;

    #[ORM\Column(name: 'is_chat', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $isChat = false;

    #[ORM\Column(name: 'is_two_factor_auth', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $isTwoFactorAuth = false;

    #[ORM\Column(name: 'is_external_file_requests', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $isExternalFileRequests = false;

    #[ORM\Column(name: 'is_docu_sign', type: 'boolean', nullable: true, options: ['default' => false])]
    private ?bool $isDocuSign = false;

    #[ORM\Column(name: 'docu_sign_request_per_month', type: 'integer', nullable: true)]
    private ?int $docuSignRequestPerMonth = null;

    #[ORM\OneToMany(mappedBy: 'storagePlan', targetEntity: Firms::class)]
    private Collection $firms;

    public function __construct()
    {
        $this->firms = new ArrayCollection();
        $this->uuid = Uuid::v7();
    }

    public function hasFirms(): bool
    {
        return $this->firms instanceof Collection && !$this->firms->isEmpty();
    }

    /**
     * @return Collection
     */
    public function getFirms(): Collection
    {
        return new ArrayCollection($this->hasFirms() ? $this->firms->toArray() : []);
    }

    public function getStorageGiB(): float
    {
        if ($this->storage === null) {
            return 0.0;
        }

        return $this->storage / pow(1024, 3);;
    }

    public function getMaxFileSizeMB(): float
    {
        if ($this->maxFileSize === null) {
            return 0.0;
        }

        return $this->maxFileSize / pow(1024, 2);
    }

    /**
     * Human friendly label for display, e.g. "10 GB" or "10.00 GB".
     *
     * @param int $decimals Number of decimals to show for fractional GiB (default 0 or 2 as you like)
     */
    public function getStorageLabel(int $decimals = 2): string
    {
        $gib = $this->getStorageGiB();

        // if decimals is 0 show integer (10 GB), otherwise format with decimals
        if ($decimals <= 0) {
            return (string) round($gib) . ' GB';
        }

        // show a decimal representation, strip trailing zeros if you prefer
        return number_format($gib, $decimals) . ' GB';
    }

    /**
     * Human friendly label for display, e.g. "10 MB" or "10.00 MB".
     *
     * @param int $decimals Number of decimals to show for fractional GiB (default 0 or 2 as you like)
     */
    public function getMaxFileSizeLabel(int $decimals = 0): string
    {
        $mb = $this->getMaxFileSizeMB();

        // if decimals is 0 show integer (10 MB), otherwise format with decimals
        if ($decimals <= 0) {
            return (string) round($mb) . 'M';
        }

        // show a decimal representation, strip trailing zeros if you prefer
        return number_format($mb, $decimals) . 'M';
    }

    public function getId(): ?int
    {
        return $this->idStoragePlan;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    // ag: storage is stored in bytes
    public function getStorage(): ?string
    {
        return $this->storage;
    }

    public function setStorage(string $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getMaxFileSize(): ?string
    {
        return $this->maxFileSize;
    }

    public function setMaxFileSize(string $maxFileSize): self
    {
        $this->maxFileSize = $maxFileSize;

        return $this;
    }

    public function getMaxUsers(): ?int
    {
        return $this->maxUsers;
    }

    public function setMaxUsers(int $maxUsers): self
    {
        $this->maxUsers = $maxUsers;

        return $this;
    }

    public function getisExternalFileRequests(): ?bool
    {
        return $this->isExternalFileRequests;
    }

    public function setIsExternalFileRequests(bool $isExternalFileRequests): self
    {
        $this->isExternalFileRequests = $isExternalFileRequests;

        return $this;
    }

    public function getIsDocuSign(): ?bool
    {
        return $this->isDocuSign;
    }

    public function setIsDocuSign(bool $isDocuSign): self
    {
        $this->isDocuSign = $isDocuSign;

        return $this;
    }

    public function getDocuSignRequestPerMonth(): ?int
    {
        return $this->docuSignRequestPerMonth;
    }

    public function setDocuSignRequestPerMonth(?int $docuSignRequestPerMonth): self
    {
        $this->docuSignRequestPerMonth = $docuSignRequestPerMonth;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        // canonicalize: always store with 2 decimals, and when I run the UOW for audit logs it won't show a change of 150.00 -> 150
        $this->price = sprintf('%.2F', (float) $price);
        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $status): self
    {
        $this->isActive = $status;
        return $this;
    }

    public function getIsCustomPlan(): ?bool
    {
        return $this->isCustomPlan;
    }

    public function setIsCustomPlan(bool $status): self
    {
        $this->isCustomPlan = $status;
        return $this;
    }

    public function getIsChat(): ?bool
    {
        return $this->isChat;
    }

    public function setIsChat(bool $status): self
    {
        $this->isChat = $status;
        return $this;
    }

    public function getIsTwoFactorAuth(): ?bool
    {
        return $this->isTwoFactorAuth;
    }

    public function setIsTwoFactorAuth(bool $status): self
    {
        $this->isTwoFactorAuth = $status;
        return $this;
    }
}
