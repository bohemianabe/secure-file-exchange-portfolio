<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\SoftDeletableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\FirmUserProfilesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

// ag: using HasLifecycleCallBack to work in tandem with the updateDate auto feature function onPreUpdate()
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: FirmUserProfilesRepository::class)]
class FirmUserProfiles
{
    public const USER_TYPE_PRIMARY = 'primary';
    public const USER_TYPE_ADMIN = 'admin';
    public const USER_TYPE_EMPLOYEE = 'employee';

    use SoftDeletableTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_firm_user_profile', nullable: false, type: 'integer')]
    private ?int $idFirmUserProfile = null;

    #[ORM\Column(name: 'first_name', type: 'string', length: 64, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: 'string', length: 64, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'title', type: 'string', length: 64, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(name: 'phone', type: 'string', length: 36, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'signature', type: 'text', nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(name: 'notify_file_uploaded', type: 'boolean', nullable: true)]
    private ?bool $notifyFileUploaded = false;

    #[ORM\Column(name: 'notify_file_downloaded', type: 'boolean', nullable: true)]
    private ?bool $notifyFileDownloaded = false;

    #[ORM\Column(name: 'notify_client_invitation_accepted', type: 'boolean', nullable: true)]
    private ?bool $notifyClientInvitationAccepted = false;

    #[ORM\Column(name: 'notify_file_expiring_soon', type: 'boolean', nullable: true)]
    private ?bool $notifyFileExpiringSoon = false;

    #[ORM\Column(name: 'notify_file_request_completed', type: 'boolean', nullable: true)]
    private ?bool $notifyFileRequestCompleted = false;

    #[ORM\Column(name: 'notify_unread_conversation', type: 'boolean', nullable: true)]
    private ?bool $notifyUnreadConversation = false;

    #[ORM\Column(name: 'notify_signature_request_completed', type: 'boolean', nullable: true)]
    private ?bool $notifySignatureRequestCompleted = false;

    #[ORM\Column(name: 'contact_user', type: 'boolean', nullable: true)]
    private ?bool $contactUser = false;

    // ag: user could be primary, admin accountant, employee
    #[ORM\Column(name: 'user_type', type: 'string', length: 64, nullable: true)]
    private ?string $userType = self::USER_TYPE_PRIMARY;

    // ag: connects MANY firmUserProfiles TO their ONE designated firm
    #[ORM\ManyToOne(targetEntity: Firms::class, inversedBy: "firmUserProfiles")]
    #[ORM\JoinColumn(name: 'firm_id', referencedColumnName: 'id_firm', onDelete: 'CASCADE')]
    private ?Firms $firm = null;

    // ag: connects One firmUserProfile TO their Many clients
    // #[ORM\OneToMany(mappedBy: 'firmUserProfiles', targetEntity: Clients::class, cascade: ['persist', 'remove'])]
    // private ?Collection $clients = null;

    // inverse of the assignment
    #[ORM\ManyToMany(targetEntity: Clients::class, mappedBy: 'firmUserProfiles')]
    private Collection $clients;

    // ag: get all the custom messages associated with this firm user
    #[ORM\OneToMany(mappedBy: 'firmUserProfile', targetEntity: CustomMessages::class, cascade: ['persist', 'remove'])]
    private Collection $customMessages;

    // 1–1 with User (firm user) points to user name w/ email and password
    #[ORM\OneToOne(inversedBy: 'firmUserProfile', targetEntity: User::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\OneToMany(mappedBy: 'firmUserProfile', targetEntity: FileRequests::class)]
    private Collection $fileRequests;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->customMessages = new ArrayCollection();
        $this->fileRequests = new ArrayCollection();
    }

    public function hasClients(): bool
    {
        return $this->clients instanceof Collection && !$this->clients->isEmpty();
    }

    public function hasFileRequests(): bool
    {
        return $this->fileRequests instanceof Collection && !$this->fileRequests->isEmpty();
    }

    public function getFileRequests(): Collection
    {
        return new ArrayCollection($this->hasFileRequests() ? $this->fileRequests->toArray() : []);
    }

    public function hasCustomMessages(): bool
    {
        return $this->customMessages instanceof Collection && !$this->customMessages->isEmpty();
    }

    public function getCustomMessages(): Collection
    {
        return new ArrayCollection($this->hasCustomMessages() ? $this->customMessages->toArray() : []);
    }

    /**
     * @return Collection
     */
    public function getAssignedClients(?bool $activeStatus = null): Collection
    {
        if ($activeStatus === null) {
            return new ArrayCollection($this->clients->toArray());
        }

        return $this->clients->filter(function ($client) use ($activeStatus) {
            return $client->getActive() === $activeStatus;
        });

        // return new ArrayCollection($this->hasClients() ? $this->clients->toArray() : []);
    }


    public function addAssignedClient(Clients $client): self
    {
        if (!$this->clients->contains($client)) {
            $this->clients->add($client);
        }
        return $this;
    }

    public function removeAssignedClient(Clients $client): self
    {
        $this->clients->removeElement($client);
        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->userType == self::USER_TYPE_PRIMARY;
    }

    public function getFirmAccount()
    {
        return $this->getFirm() ? $this->getFirm()->getAccount() : null;
    }


    // public function getFileFolderPath()
    // {
    //     return 'uploads/files';
    // }

    // public function getFirmAccountPath()
    // {
    //     return $this->getFileFolderPath() . "/{$this->getFirm()->getAccount()}";
    // }

    // public function getUserFolderPath()
    // {
    //     return $this->getFirmAccountPath() . "/{$this->getFirm()->getId()}";
    // }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    // ****************************** ag: setters and getters **********************************************
    public function getId(): ?int
    {
        return $this->idFirmUserProfile;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getNotifyFileUploaded(): ?bool
    {
        return $this->notifyFileUploaded;
    }

    public function setNotifyFileUploaded(int $notifyFileUploaded): self
    {
        $this->notifyFileUploaded = $notifyFileUploaded;

        return $this;
    }

    public function getNotifyFileDownloaded(): ?bool
    {
        return $this->notifyFileDownloaded;
    }

    public function setNotifyFileDownloaded(int $notifyFileDownloaded): self
    {
        $this->notifyFileDownloaded = $notifyFileDownloaded;

        return $this;
    }

    public function getNotifyClientInvitationAccepted(): ?bool
    {
        return $this->notifyClientInvitationAccepted;
    }

    public function setNotifyClientInvitationAccepted(int $notifyClientInvitationAccepted): self
    {
        $this->notifyClientInvitationAccepted = $notifyClientInvitationAccepted;

        return $this;
    }

    public function getNotifyFileExpiringSoon(): ?bool
    {
        return $this->notifyFileExpiringSoon;
    }

    public function setNotifyFileExpiringSoon(int $notifyFileExpiringSoon): self
    {
        $this->notifyFileExpiringSoon = $notifyFileExpiringSoon;

        return $this;
    }

    public function getNotifyFileRequestCompleted(): ?bool
    {
        return $this->notifyFileRequestCompleted;
    }

    public function setNotifyFileRequestCompleted(int $notifyFileRequestCompleted): self
    {
        $this->notifyFileRequestCompleted = $notifyFileRequestCompleted;

        return $this;
    }

    public function getNotifyUnreadConversation(): ?bool
    {
        return $this->notifyUnreadConversation;
    }

    public function setNotifyUnreadConversation(int $notifyUnreadConversation): self
    {
        $this->notifyUnreadConversation = $notifyUnreadConversation;

        return $this;
    }

    public function getNotifySignatureRequestCompleted(): ?bool
    {
        return $this->notifySignatureRequestCompleted;
    }

    public function setNotifySignatureRequestCompleted(int $notifySignatureRequestCompleted): self
    {
        $this->notifySignatureRequestCompleted = $notifySignatureRequestCompleted;

        return $this;
    }

    public function getContactUser(): ?bool
    {
        return $this->contactUser;
    }

    public function setContactUser(bool $contactUser): self
    {
        $this->contactUser = $contactUser;

        return $this;
    }

    public function getUserType(): ?string
    {
        return $this->userType;
    }

    public function setUserType(?string $userType): self
    {
        $this->userType = $userType;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
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

    public static function getUserTypes(): array
    {
        return [
            self::USER_TYPE_PRIMARY,
            self::USER_TYPE_ADMIN,
            self::USER_TYPE_EMPLOYEE,
        ];
    }

    public function getInitials(): string
    {
        $words = explode(' ', trim($this->getFullName()));

        $initials = '';

        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        return substr($initials, 0, 2);
    }
}
