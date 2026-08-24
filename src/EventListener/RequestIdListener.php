<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequestEvent', priority: 512)]
final class RequestIdListener
{
    public function onRequestEvent(RequestEvent $event): void
    {
        $req = $event->getRequest();
        $rid = $req->headers->get('X-Request-Id') ?? bin2hex(random_bytes(12));
        $req->attributes->set('request_id', $rid);
    }
}
