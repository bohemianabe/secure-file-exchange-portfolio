<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class TenantUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!method_exists($user, 'getRoles')) {
            throw new CustomUserMessageAccountStatusException('Invalid credentials.');
        }

        // ag: important to check this especially for 2fa logic
        if (!in_array('ROLE_FIRM', $user->getRoles(), true) && !in_array('ROLE_CLIENT', $user->getRoles(), true)) {
            throw new CustomUserMessageAccountStatusException('Invalid credentials.');
        }

        if (method_exists($user, 'getIsActive') && !$user->getIsActive()) {
            throw new CustomUserMessageAccountStatusException('Your account is inactive.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new CustomUserMessageAccountStatusException('Invalid credentials.');
        }
    }
}
