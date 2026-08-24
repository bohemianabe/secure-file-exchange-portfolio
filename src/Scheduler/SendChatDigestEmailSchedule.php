<?php

namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('default')]
final class SendChatDigestEmailSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // ag: at midnight run chat digest to let users know if they have unread messages to read. https://symfony.com/doc/current/scheduler.html / https://symfonycasts.com/screencast/mailtrap/bonus-symfony-scheduler
                RecurringMessage::cron('#midnight', new RunCommandMessage('app:send-chat-digest-notifications'), new \DateTimeZone('America/New_York')),
            )
            ->stateful($this->cache)
        ;
    }
}
