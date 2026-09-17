<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lets `#[MapRequestPayload(acceptFormat: 'json')]` accept RFC 7396 bodies
 * sent as `application/merge-patch+json`; they are plain JSON on the wire.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
final class MergePatchJsonRequestListener
{
    public function __invoke(RequestEvent $event): void
    {
        $event->getRequest()->setFormat('json', [
            'application/json',
            'application/x-json',
            'application/merge-patch+json',
        ]);
    }
}
