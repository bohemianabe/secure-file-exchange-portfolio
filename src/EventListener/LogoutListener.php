<?php

namespace App\EventListener;

use App\Logging\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutListener
{
    public function __construct(private AuditLogger $audit) {}

    #[AsEventListener]
    public function onLogoutEvent(LogoutEvent $event): void
    {
        // The token may be null (stateless firewall), so guard it.
        $user = $event->getToken()?->getUser();
        // Extract an ID safely (object implementing UserInterface vs string username)
        $userId = null;
        if (\is_object($user) && \method_exists($user, 'getId')) {
            $userId = (string) $user->getId();
        } elseif (\is_string($user)) {
            // Some authenticators set the username string as "user"
            $userId = $user;
        }

        $this->audit->log('logout', 'User', $userId, true);
    }
}
