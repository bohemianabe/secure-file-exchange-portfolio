<?php

namespace App\Twig;

use App\Enum\AnnouncementAudience;
use App\Repository\AnnouncementsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AnnouncementExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly AnnouncementsRepository $announcementRepository,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {}

    public function getGlobals(): array
    {
        $audience = AnnouncementAudience::All;

        $user = $this->security->getUser();

        if ($user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $audience = AnnouncementAudience::Admin;
                // $audience = 'admin';
            } elseif (in_array('ROLE_FIRM', $user->getRoles(), true)) {
                $audience = AnnouncementAudience::Firm;
                // $audience = 'firm';
            } elseif (in_array('ROLE_CLIENT', $user->getRoles(), true)) {
                $audience = AnnouncementAudience::Client;
                // $audience = 'client';
            }
        }
        $announcement = $this->announcementRepository->findActiveForAudience(
            $audience,
            new \DateTimeImmutable()
        );

        $request = $this->requestStack->getCurrentRequest();
        // ag: add this check since twig runs globally, and in functions like messenger that sends out emails with twig, sessions will not exist esp. if php bin/console messenger:consume is ran in the cli.
        if (!$request || !$request->hasSession()) {
            return [];
        }

        $session = $request->getSession();

        $dismissed = $session?->get('dismissed_announcements', []);

        if ($announcement && in_array($announcement->getId(), $dismissed, true)) {
            $announcement = null;
        }
        return [
            'site_announcement' => $announcement,
        ];
    }
}
