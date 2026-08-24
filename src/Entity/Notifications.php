<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\SoftDeletableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Enum\NotificationResource;
use App\Enum\NotificationType;
use App\Repository\NotificationsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationsRepository::class)]
class Notifications
{

    use SoftDeletableTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_notification', type: 'integer', nullable: false)]
    private ?int $idNotification = null;

    // ag: if the user is deleted cascade down to delete their notifications too. This is the user who should receive the notification not the user that created the action.
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_user', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $recipientUser;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_user', referencedColumnName: 'id', nullable: true)]
    private ?User $actorUser = null;

    #[ORM\ManyToOne(targetEntity: Firms::class)]
    #[ORM\JoinColumn(name: 'firm_id', referencedColumnName: 'id_firm', nullable: true)]
    private ?Firms $firm = null;

    // ag: commenting out clients, but may be something I use in the future for auditing.
    // #[ORM\ManyToOne(targetEntity: Clients::class)]
    // #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id_client', nullable: true)]
    // private Clients $client;

    #[ORM\Column(name: 'type', length: 50, nullable: false, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\Column(name: 'resource_type', length: 50, nullable: false, enumType: NotificationResource::class)]
    private NotificationResource $resourceType;

    #[ORM\Column(name: 'resource_id', length: 36, nullable: false)]
    private string $resourceId;

    #[ORM\Column(name: 'title', length: 255, nullable: false)]
    private string $title;

    #[ORM\Column(name: 'message', type: 'text', nullable: false)]
    private string $message;

    #[ORM\Column(name: 'data', type: 'json', nullable: true)]
    private ?array $data = [];

    #[ORM\Column(name: 'is_read', type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(name: 'email_eligible', type: 'boolean', options: ['default' => false])]
    private bool $emailEligible = false;

    #[ORM\Column(name: 'count', type: 'integer', nullable: true)]
    private ?int $count = null;

    #[ORM\Column(name: 'link_url', type: 'string', length: 256, nullable: true)]
    private ?string $linkUrl = null;

    #[ORM\Column(name: 'link_icon', type: 'string', length: 126, nullable: true)]
    private ?string $linkIcon = null;

    #[ORM\Column(name: 'email_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;

    public function getId(): ?int
    {
        return $this->idNotification;
    }

    public function getRecipientUser(): User
    {
        return $this->recipientUser;
    }

    public function setRecipientUser(?User $recipientUser): self
    {
        $this->recipientUser = $recipientUser;
        return $this;
    }

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }

    public function setActorUser(?User $actorUser): self
    {
        $this->actorUser = $actorUser;
        return $this;
    }

    public function getFirm(): Firms
    {
        return $this->firm;
    }

    public function setFirm(?Firms $firm): self
    {
        $this->firm = $firm;
        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function setType(?NotificationType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getResourceType(): NotificationResource
    {
        return $this->resourceType;
    }

    public function setResourceType(?NotificationResource $resourceType): self
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function setResourceId(?string $resourceId): self
    {
        $this->resourceId = $resourceId;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getIsRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(int $isRead): self
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getEmailEligible(): ?bool
    {
        return $this->emailEligible;
    }

    public function setEmailEligible(int $emailEligible): self
    {
        $this->emailEligible = $emailEligible;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(?int $count): self
    {
        $this->count = $count;
        return $this;
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function setLinkUrl(?string $linkUrl): self
    {
        $this->linkUrl = $linkUrl;
        return $this;
    }

    public function getLinkIcon(): ?string
    {
        return $this->linkIcon;
    }

    public function setLinkIcon(?string $linkIcon): self
    {
        $this->linkIcon = $linkIcon;
        return $this;
    }

    public function incrementCount(): self
    {
        $this->count = ($this->count ?? 0) + 1;

        return $this;
    }

    public function getEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->emailSentAt;
    }

    public function setEmailSentAt(\DateTimeImmutable $emailSentAt): self
    {
        $this->emailSentAt = $emailSentAt;
        return $this;
    }
}
