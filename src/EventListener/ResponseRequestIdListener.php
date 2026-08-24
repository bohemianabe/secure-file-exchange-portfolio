<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponseEvent')]
final class ResponseRequestIdListener
{
    public function onResponseEvent(ResponseEvent $event): void
    {
        if ($rid = $event->getRequest()->attributes->get('request_id')) {
            $event->getResponse()->headers->set('X-Request-Id', $rid);
        }
    }
}
