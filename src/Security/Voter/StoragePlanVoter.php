<?php

namespace App\Security\Voter;

use App\Entity\Clients;
use App\Entity\Firms;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class StoragePlanVoter extends Voter
{
    public const CHAT = 'FEATURE_CHAT';
    public const DOCUSIGN = 'FEATURE_DOCUSIGN';
    public const EXTERNAL_FILE_REQUESTS = 'FEATURE_EXTERNAL_FILE_REQUESTS';
    public const TWO_FACTOR_AUTH = 'FEATURE_TWO_FACTOR_AUTH';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::CHAT,
            self::DOCUSIGN,
            self::EXTERNAL_FILE_REQUESTS,
            self::TWO_FACTOR_AUTH,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $firm = match (true) {
            $subject instanceof Clients => $subject->getFirm(),
            $subject instanceof Firms => $subject,
            default => null,
        };

        if (!$firm) {
            return false;
        }

        $plan = $firm->getStoragePlan();

        if (!$plan) {
            return false;
        }

        return match ($attribute) {
            self::CHAT => $plan->getIsChat(),
            self::DOCUSIGN => $plan->getIsDocuSign(),
            self::EXTERNAL_FILE_REQUESTS => $plan->getIsExternalFileRequests(),
            self::TWO_FACTOR_AUTH => $plan->getIsTwoFactorAuth(),
            default => false,
        };
    }
}
