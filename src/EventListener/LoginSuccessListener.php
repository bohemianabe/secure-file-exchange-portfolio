<?php

namespace App\EventListener;

use App\Logging\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginSuccessListener
{
    public function __construct(private AuditLogger $audit) {}

    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {

        $user = $event->getAuthenticatedToken()->getUser();
        // ag: add a user_session_login_at variable for the notification polling
        $request = $event->getRequest();
        $request->getSession()->set('user_session_login_at', new \DateTimeImmutable());
        $this->audit->log('login', 'User', method_exists($user, 'getId') ? (string)$user->getId() : null, true);
    }
}
