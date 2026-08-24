<?php

namespace App\Security\Voter;

use App\Entity\Files;
use App\Entity\User;
use App\Entity\FirmUserProfiles;
use App\Entity\ClientUserProfiles;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FileVoter extends Voter
{
    public const PREVIEW            = 'FILE_PREVIEW';
    public const DOWNLOAD           = 'FILE_DOWNLOAD';
    public const DELETE             = 'FILE_DELETE';
    public const PERMANANTDELETE    = 'FILE_DELETE_PERMANANT';
    public const EDIT               = 'FILE_EDIT';
    public const RESTORE            = 'FILE_RESTORE';
    public const ACCESS             = 'FILE_ACCESS';

    public function __construct(
        private readonly Security $security,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::PREVIEW, self::DOWNLOAD, self::DELETE, self::EDIT, self::RESTORE, self::PERMANANTDELETE, self::ACCESS], true)
            && $subject instanceof Files;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Files $file */
        $file = $subject;

        return match ($attribute) {
            self::PREVIEW           => $this->canPreview($file, $user),
            self::DOWNLOAD          => $this->canDownload($file, $user),
            self::DELETE            => $this->canDelete($file, $user),
            self::PERMANANTDELETE   => $this->canDeletePermanant($file, $user),
            self::EDIT              => $this->canEdit($file, $user),
            self::RESTORE           => $this->canRestore($file, $user),
            self::ACCESS            => $this->canAccessFile($file, $user),
            default => false,
        };
    }

    private function canPreview(Files $file, User $user): bool
    {
        // For now, preview and download can share the same rules.
        return $this->canAccessFile($file, $user);
    }

    private function canDownload(Files $file, User $user): bool
    {
        return $this->canAccessFile($file, $user);
    }

    private function canAccessFile(Files $file, User $user): bool
    {
        // ag: Expired files should not be accessible...
        // if ($file->getExpirationDate() !== null && $file->getExpirationDate() < new \DateTimeImmutable()) {
        //     return false;
        // }

        // Soft-deleted files should not be accessible.
        if (method_exists($file, 'getDeletedAt') && $file->getDeletedAt() !== null) {
            return false;
        }

        $userProfile = $user->getUserProfile();

        if ($userProfile instanceof FirmUserProfiles) {
            // Uploader can access their own file.
            if ($file->getUploadedByFirmUser() === $userProfile) {
                return true;
            }
        }

        if ($userProfile instanceof ClientUserProfiles) {
            // Uploader can access their own file.
            if ($file->getUploadedByClientUser() === $userProfile) {
                return true;
            }
        }

        if ($userProfile instanceof FirmUserProfiles) {
            return $this->firmUserCanAccess($file, $userProfile);
        }

        if ($userProfile instanceof ClientUserProfiles) {
            return $this->clientUserCanAccess($file, $userProfile);
        }

        return false;
    }

    private function firmUserCanAccess(Files $file, FirmUserProfiles $profile): bool
    {
        $firm = $profile->getFirm();

        // Firm user can only access files belonging to their firm.
        if ($file->getFirm() !== $firm) {
            return false;
        }

        // Primary/admin firm users can access all firm files.
        if (in_array($profile->getUserType(), ['primary', 'admin'], true)) {
            return true;
        }

        // If file belongs to a specific client, employee must be assigned to that client.
        if ($file->getClient() !== null) {
            return $profile->getAssignedClients()->contains($file->getClient());
        }

        // Public firm-wide file.
        return true;
    }

    private function clientUserCanAccess(Files $file, ClientUserProfiles $profile): bool
    {
        $client = $profile->getClient();

        // Client user can access files specifically assigned to their client.
        if ($file->getClient() === $client) {
            return true;
        }

        // Public/all-client firm file.
        if ($file->getClient() === null && $file->getFirm() === $client->getFirm()) {
            return true;
        }

        return false;
    }

    private function canDelete(Files $file, User $user): bool
    {
        if (method_exists($file, 'getDeletedAt') && $file->getDeletedAt() !== null) {
            return false;
        }

        $userProfile = $user->getUserProfile();

        // ag: a firmUser who uploaded the file can delete their own file.
        // if ($file->getUploadedByFirmUser() === $userProfile) {
        //     return true;
        // }

        if (!$userProfile instanceof FirmUserProfiles) {
            return false;
        }

        // Firm user must belong to the same firm as the file.
        if ($file->getFirm() !== $userProfile->getFirm()) {
            return false;
        }

        // Only primary/admin firm users can delete other users' files.
        // return in_array($userProfile->getUserType(), ['primary', 'admin'], true);

        // ag: if the file has a client and is not an admin
        if ($file->getClient()) {
            if (!in_array($userProfile->getUserType(), ['primary', 'admin'], true) && !in_array($file->getClient(), $userProfile->getAssignedClients()->toArray(), true)) {
                return false;
            }
        }

        return true;
    }

    private function canDeletePermanant(Files $file, User $user): bool
    {
        if (method_exists($file, 'getDeletedAt') && $file->getDeletedAt() == null) {
            return false;
        }

        $userProfile = $user->getUserProfile();

        // ag: a firmUser who uploaded the file can delete their own file.
        if ($file->getUploadedByFirmUser() === $userProfile) {
            return true;
        }

        if (!$userProfile instanceof FirmUserProfiles) {
            return false;
        }

        // Firm user must belong to the same firm as the file.
        if ($file->getFirm() !== $userProfile->getFirm()) {
            return false;
        }

        // Only primary/admin firm users can delete other users' files.
        return in_array($userProfile->getUserType(), ['primary', 'admin'], true);
    }

    private function canEdit(Files $file, User $user): bool
    {
        if (method_exists($file, 'getDeletedAt') && $file->getDeletedAt() !== null) {
            return false;
        }

        $userProfile = $user->getUserProfile();

        // Firm users
        if ($userProfile instanceof FirmUserProfiles) {
            if ($file->getFirm() !== $userProfile->getFirm()) {
                return false;
            }

            $client = $file->getClient();
            if (!is_null($client)) {
                if (!in_array($userProfile->getUserType(), ['primary', 'admin'], true) && !in_array($client, $userProfile->getAssignedClients()->toArray(), true)) {
                    return false;
                }
            }
        }

        // Client users
        if ($userProfile instanceof ClientUserProfiles) {
            if ($file->getClient() !== $userProfile->getClient()) {
                return false;
            }
        }

        return true;
    }

    private function canRestore(Files $file, User $user): bool
    {
        // Only soft-deleted files can be restored.
        if ($file->getDeletedAt() === null) {
            return false;
        }

        $userProfile = $user->getUserProfile();

        if (!$userProfile instanceof FirmUserProfiles) {
            return false;
        }

        // Firm user can only restore files belonging to their firm.
        if ($file->getFirm() !== $userProfile->getFirm()) {
            return false;
        }

        // Primary/admin firm users can restore firm files.
        $client = $file->getClient();
        if (!is_null($client)) {
            if (!in_array($userProfile->getUserType(), ['primary', 'admin'], true) && !in_array($client, $userProfile->getAssignedClients()->toArray(), true)) {
                return false;
            }
        }

        return true;
    }
}
