<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $now = new \DateTimeImmutable();
        $lastSeenAt = $user->getLastSeenAt();

        // Only write to DB if last update was more than 60 seconds ago
        if ($lastSeenAt && $lastSeenAt > $now->modify('-60 seconds')) {
            return;
        }

        $user->setLastSeenAt($now);
        $this->em->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
