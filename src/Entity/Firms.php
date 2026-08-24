<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\SoftDeletableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\FirmsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FirmsRepository::class)]
class Firms
{
    use SoftDeletableTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_firm', nullable: false, type: 'integer')]
    private ?int $idFirm = null;

    #[ORM\Column(name: 'name', type: 'string', length: 128, unique: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'addr_1', type: 'string', length: 64, nullable: true)]
    private ?string $addr1 = null;

    #[ORM\Column(name: 'addr_2', type: 'string', length: 64, nullable: true)]
    private ?string $addr2 = null;

    #[ORM\Column(name: 'city', type: 'string', length: 64, nullable: true)]
    private ?string $city = null;

    #[ORM\ManyToOne(inversedBy: 'firms', targetEntity: States::class)]
    #[ORM\JoinColumn(name: 'state_id', referencedColumnName: 'id_state', nullable: true)]
    private ?States $state = null;

    #[ORM\Column(name: 'region_name', type: 'string', length: 128, nullable: true)]
    private ?string $regionName = null;

    #[ORM\Column(name: 'zip', type: 'string', length: 64, nullable: true)]
    private ?string $zip = null;

    #[ORM\ManyToOne(inversedBy: 'firms', targetEntity: Countries::class)]
    #[ORM\JoinColumn(name: 'id_country', referencedColumnName: 'id_country', nullable: false)]
    private ?Countries $country = null;

    #[ORM\Column(name: 'phone', type: 'string', length: 28, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'logo', type: 'string', length: 81, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(name: 'banner_image', type: 'string', length: 81, nullable: true)]
    private ?string $bannerImage = null;

    #[ORM\Column(name: 'active', type: 'boolean', nullable: true, options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(name: 'account', type: 'string', length: 64, unique: true)]
    private ?string $account = null;

    #[ORM\Column(name: 'file_duration_days', type: 'integer', length: 16, nullable: true, options: ['default' => 60])]
    private ?int $fileDurationDays = 60;

    #[ORM\Column(type: 'string', length: 64, nullable: false, options: ['comment' => 'IANA TZ like America/New_York'])]
    private string $timezone = 'UTC';

    // ag: currently not used, but will keep for possible future implementation
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $pendingStoragePlanStartDate = null;

    #[ORM\OneToMany(mappedBy: 'firm', targetEntity: FirmUserProfiles::class, cascade: ['remove'], orphanRemoval: true)]
    private ?Collection $firmUserProfiles = null;

    #[ORM\ManyToOne(inversedBy: 'firms', targetEntity: StoragePlans::class)]
    #[ORM\JoinColumn(name: 'storage_plan_id', referencedColumnName: 'id_storage_plan', nullable: false)]
    private ?StoragePlans $storagePlan = null;

    // ag: datapoint to keep track of changed storage plans made by the firm user
    // #[ORM\ManyToOne(inversedBy: 'firms', targetEntity: StoragePlans::class)]
    #[ORM\ManyToOne(targetEntity: StoragePlans::class)]
    #[ORM\JoinColumn(name: 'pending_storage_plan_id', referencedColumnName: 'id_storage_plan', nullable: true)]
    private ?StoragePlans $pendingStoragePlan = null;

    // #[ORM\OneToMany(mappedBy: 'firm', targetEntity: Clients::class)]
    #[ORM\OneToMany(mappedBy: 'firm', targetEntity: Clients::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id_client')]
    private ?Collection $clients = null;

    #[ORM\OneToMany(mappedBy: 'firm', targetEntity: Files::class)]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id_file')]
    private Collection $files;

    #[ORM\OneToMany(mappedBy: 'firm', targetEntity: FileRequests::class)]
    #[ORM\JoinColumn(name: 'file_request_id', referencedColumnName: 'id_file_request')]
    private Collection $fileRequests;

    // ag: to make to the correct profile
    #[ORM\OneToOne(mappedBy: 'firm', targetEntity: FirmSubscriptions::class, cascade: ['persist', 'remove'])]
    private ?FirmSubscriptions $firmSubscription = null;

    public function __construct()
    {
        $this->firmUserProfiles = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->fileRequests = new ArrayCollection();
    }

    public function hasFileRequests(): bool
    {
        return $this->fileRequests instanceof Collection && !$this->fileRequests->isEmpty();
    }

    public function getFileRequests(): Collection
    {
        return new ArrayCollection($this->hasFileRequests() ? $this->fileRequests->toArray() : []);
    }

    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function getFilesCount()
    {
        return $this->files instanceof Collection ? $this->files->count() : 0;
    }

    public function getUsedStorageBytes(): int
    {
        $bytes = 0;
        foreach ($this->files->toArray() as $file) {
            $bytes += intval($file->getSizeBytes());
        }

        return $bytes;
    }

    public function getRemainingStorageBytes(): int
    {
        if (!$this->storagePlan instanceof StoragePlans) {
            return 0;
        }

        $planBytes = (int) $this->storagePlan->getStorage();
        $usedBytes = $this->getUsedStorageBytes();

        return max(0, $planBytes - $usedBytes);
    }

    // ag: logic to get all firmUserProfiles associated with the firm
    public function hasFirmUserProfiles(): bool
    {
        return $this->firmUserProfiles instanceof Collection && !$this->firmUserProfiles->isEmpty();
    }

    /**
     * @return Collection
     */
    public function getFirmUserProfiles(?bool $activeStatus = null): Collection
    {
        if ($activeStatus === null) {
            return new ArrayCollection($this->hasFirmUserProfiles() ? $this->firmUserProfiles->toArray() : []);
            // return new ArrayCollection($this->clients->toArray());
        }

        return $this->firmUserProfiles->filter(function ($fup) use ($activeStatus) {
            return $fup->getUser()->getIsActive() === $activeStatus;
        });
    }

    public function hasPrimaryUser(): bool
    {
        return $this->hasFirmUserProfiles() && $this->getFirmUserProfiles()->filter(fn($firmUserProfiles) => $firmUserProfiles->isPrimary())->first() instanceof FirmUserProfiles;
    }

    /**
     * fetch the firms primary user
     *
     * @return FirmUserProfiles
     */
    public function getPrimaryUser(): FirmUserProfiles
    {
        return $this->getFirmUserProfiles()
            ->filter(fn(FirmUserProfiles $fup) => $fup->isPrimary())
            ->first() ?: null;
    }

    public function hasClients(): bool
    {
        return $this->clients instanceof Collection && !$this->clients->isEmpty();
    }

    /**
     * @return Collection
     */
    public function getClients(?bool $activeStatus = null): Collection
    {

        if ($activeStatus === null) {
            return new ArrayCollection($this->clients->toArray());
        }

        return $this->clients->filter(function ($client) use ($activeStatus) {
            return $client->getActive() === $activeStatus;
        });
    }

    // ***************************** ag: setters and getters below here ******************************************

    public function getId(): ?int
    {
        return $this->idFirm;
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

    public function getAddr1(): ?string
    {
        return $this->addr1;
    }
    public function setAddr1(?string $addr1): self
    {
        $this->addr1 = $addr1;
        return $this;
    }

    public function getAddr2(): ?string
    {
        return $this->addr2;
    }
    public function setAddr2(?string $addr2): self
    {
        $this->addr2 = $addr2;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }
    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getState(): ?States
    {
        return $this->state;
    }

    public function setState(?States $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getRegionName(): ?string
    {
        return $this->regionName;
    }

    public function setRegionName(?string $regionName): self
    {
        $this->regionName = $regionName;
        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }
    public function setZip(?string $zip): self
    {
        $this->zip = $zip;
        return $this;
    }

    public function getCountry(): ?Countries
    {
        return $this->country;
    }
    public function setCountry(?Countries $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }
    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;
        return $this;
    }
    public function getBannerImage(): ?string
    {
        return $this->bannerImage;
    }
    public function setBannerImage(?string $bannerImage): self
    {
        $this->bannerImage = $bannerImage;
        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }
    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getAccount(): ?string
    {
        return $this->account;
    }
    public function setAccount(?string $account): self
    {
        $this->account = $account;
        return $this;
    }

    public function getFileDurationDays(): ?int
    {
        return $this->fileDurationDays;
    }
    public function setFileDurationDays(int $fileDurationDays): self
    {
        $this->fileDurationDays = $fileDurationDays;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getPendingStoragePlanStartDate(): ?\DateTimeImmutable
    {
        return $this->pendingStoragePlanStartDate;
    }

    public function setPendingStoragePlanStartDate(\DateTimeImmutable $pendingStoragePlanStartDate): self
    {
        $this->pendingStoragePlanStartDate = $pendingStoragePlanStartDate;
        return $this;
    }

    public function hasCustomStoragePlan(): bool
    {
        return $this->storagePlan->getIsCustomPlan();
    }

    public function getStoragePlan(): ?StoragePlans
    {
        return $this->storagePlan;
    }

    public function setStoragePlan(StoragePlans $storagePlan): self
    {
        $this->storagePlan = $storagePlan;
        return $this;
    }

    public function getPendingStoragePlan(): ?StoragePlans
    {
        return $this->pendingStoragePlan;
    }

    public function setPendingStoragePlan(StoragePlans $pendingStoragePlan): self
    {
        $this->pendingStoragePlan = $pendingStoragePlan;
        return $this;
    }

    public function getFirmSubscription(): FirmSubscriptions
    {
        return $this->firmSubscription;
    }

    public function setFirmSubscription(FirmSubscriptions $firmSubscription): self
    {
        $this->firmSubscription = $firmSubscription;
        return $this;
    }
}
