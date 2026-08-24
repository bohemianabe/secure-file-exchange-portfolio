<?php

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\SoftDeletableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface as TOTPTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TOTPTwoFactorInterface, PreferredProviderInterface, EmailTwoFactorInterface, BackupCodeInterface
{
    use SoftDeletableTrait;
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: "email", type: "string", length: 180, nullable: false, unique: true)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column(name: "roles", type: "json", nullable: false)]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(name: 'password', type: 'string', length: 255, nullable: true)]
    private ?string $password = null;

    // ag: set the default to false since all forms with be a checkbox, and if no value is passed through it's false
    #[ORM\Column(name: 'is_active', type: 'boolean', nullable: false, options: ['default' => true])]
    private ?bool $isActive = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $firstLoginToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $firstLoginTokenExpiresAt = null;

    // ag: to make to the correct profile
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: FirmUserProfiles::class, cascade: ['persist', 'remove'])]
    private ?FirmUserProfiles $firmUserProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ClientUserProfiles::class, cascade: ['persist', 'remove'])]
    private ?ClientUserProfiles $clientUserProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: AdminUserProfiles::class, cascade: ['persist', 'remove'])]
    private ?AdminUserProfiles $adminUserProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ResetPasswordRequest::class, cascade: ['persist', 'remove'])]
    private ?ResetPasswordRequest $resetPasswordRequest = null;

    #[ORM\Column(name: 'preferred_two_factor_provider', type: 'string', length: 68, nullable: true)]
    private ?string $preferredTwoFactorProvider = 'totp';

    // ag: added these columns for 2fa functionality/ controls whether the login should challenge for 2FA.
    #[ORM\Column(name: 'two_factor_enabled', type: 'boolean', options: ['default' => false])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(name: 'is_email_auth_enabled', type: 'boolean', options: ['default' => false])]
    private bool $isEmailAuthEnabled = false;

    // ag: stores the shared TOTP secret.
    #[ORM\Column(name: 'totp_secret', type: 'string', length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(name: 'auth_code', type: 'string', length: 255, nullable: true)]
    private ?string $authCode = null;

    // ag: gives recovery if the user loses their authenticator app
    #[ORM\Column(name: 'backup_codes', type: 'json', nullable: true)]
    private ?array $backupCodes = [];

    //ag: helps distinguish “setup started” from “setup completed.”
    #[ORM\Column(name: 'two_factor_confirmed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $twoFactorConfirmedAt = null;

    #[ORM\Column(name: 'email_auth_code_confirmed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailAuthCodeCreatedAt = null;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(name: 'last_notification_popup_seen_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastNotificationPopupSeenAt = null;

    // ag: TODO future language build out
    // #[ORM\Column(name: 'locale', type: 'string', length: 5)]
    // private string $locale = 'en';

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * A visual identifier that represents this user.
     * ag: the email will be the unique identifier for a user
     * 
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function isFirmOrClient(): bool
    {
        return in_array('ROLE_CLIENT', $this->roles, true)
            || in_array('ROLE_FIRM', $this->roles, true);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $status): self
    {
        $this->isActive = $status;

        return $this;
    }

    public function getFirstLoginToken(): ?string
    {
        return $this->firstLoginToken;
    }

    public function setFirstLoginToken(?string $token): self
    {
        $this->firstLoginToken = $token;
        return $this;
    }

    public function getFirstLoginTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->firstLoginTokenExpiresAt;
    }

    public function setFirstLoginTokenExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->firstLoginTokenExpiresAt = $expiresAt;
        return $this;
    }

    public function isFirstLoginTokenValid(string $token): bool
    {
        return $this->firstLoginToken === $token &&
            $this->firstLoginTokenExpiresAt &&
            $this->firstLoginTokenExpiresAt > new \DateTimeImmutable();
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }


    public function setLastSeenAt(\DateTimeImmutable $date)
    {

        $this->lastSeenAt = $date;

        return $this;
    }

    public function isOnline(): bool
    {
        if (!$this->lastSeenAt) {
            return false;
        }

        return $this->lastSeenAt > new \DateTimeImmutable('-5 minutes');
    }

    // ag: this returns which of the profiles the user is associated with.
    public function getUserProfile(): AdminUserProfiles|FirmUserProfiles|ClientUserProfiles|null
    {
        if ($this->adminUserProfile !== null) {
            return $this->adminUserProfile;
        }

        if ($this->firmUserProfile !== null) {
            return $this->firmUserProfile;
        }

        if ($this->clientUserProfile !== null) {
            return $this->clientUserProfile;
        }

        return null; // no profile attached
    }

    public function getTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $status): self
    {
        $this->twoFactorEnabled = $status;
        return $this;
    }

    public function isEmailAuthEnabled(): bool
    {
        return $this->isEmailAuthEnabled;
    }

    public function setIsEmailAuthEnabled(bool $status): self
    {
        $this->isEmailAuthEnabled = $status;
        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    public function getPreferredTwoFactorProvider(): ?string
    {
        return $this->preferredTwoFactorProvider;
    }

    public function setPreferredTwoFactorProvider(?string $preferredTwoFactorProvider): self
    {
        $this->preferredTwoFactorProvider = $preferredTwoFactorProvider;
        return $this;
    }

    public function getEmailAuthCode(): ?string
    {
        if (null === $this->authCode) {
            return null;
        }

        // Check expiration (10 minutes)
        if ($this->emailAuthCodeCreatedAt === null) {
            return null;
        }

        $expiresAt = $this->emailAuthCodeCreatedAt->modify('+10 minutes');

        if (new \DateTimeImmutable() > $expiresAt) {
            return null;
        }

        return $this->authCode;
    }

    public function setEmailAuthCode(?string $authCode): void
    {
        $this->authCode = $authCode;
        $this->emailAuthCodeCreatedAt = new \DateTimeImmutable();
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): self
    {
        $this->backupCodes = $backupCodes;
        return $this;
    }

    public function getTwoFactorConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->twoFactorConfirmedAt;
    }

    public function setTwoFactorConfirmedAt(?\DateTimeImmutable $confirmedAt): self
    {
        $this->twoFactorConfirmedAt = $confirmedAt;
        return $this;
    }

    public function getEmailAuthCodeCreatedAt(): ?\DateTimeImmutable
    {
        return $this->emailAuthCodeCreatedAt;
    }

    public function setEmailAuthCodeCreatedAt(?\DateTimeImmutable $confirmedAt): self
    {
        $this->emailAuthCodeCreatedAt = $confirmedAt;
        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->twoFactorEnabled && !empty($this->totpSecret);
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getEmail();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (!$this->totpSecret) {
            return null;
        }

        // You could persist the other configuration options in the user entity to make it individual per user.
        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getEmailAuthRecipient(): string
    {
        return $this->email;
    }

    public function isBackupCode(string $code): bool
    {
        if (!is_null($this->backupCodes) && !empty($this->backupCodes)) {
            foreach ($this->backupCodes as $hashedCode) {
                if (password_verify($code, $hashedCode)) {
                    return true;
                }
            }
        }

        return false;
        // return in_array($code, $this->backupCodes);
    }

    /**
     * Invalidate a backup code
     */
    public function invalidateBackupCode(string $code): void
    {

        foreach ($this->backupCodes as $index => $hash) {
            if (password_verify($code, $hash)) {
                unset($this->backupCodes[$index]);
            }
        }
    }

    /**
     * Add a backup code
     */
    public function addBackUpCode(string $backUpCode): void
    {
        if (!in_array($backUpCode, $this->backupCodes)) {
            $this->backupCodes[] = $backUpCode;
        }
    }

    public function getLastNotificationPopupSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastNotificationPopupSeenAt;
    }

    public function setLastNotificationPopupSeenAt(?\DateTimeImmutable $lastNotificationPopupSeenAt): self
    {
        $this->lastNotificationPopupSeenAt = $lastNotificationPopupSeenAt;

        return $this;
    }

    // public function getLocale(): string 
    // {
    //     return $this->locale;
    // }

    // public function setLocale(string $locale): self 
    // {
    //     $this->locale = $locale;
    //     return $this;
    // }
}
