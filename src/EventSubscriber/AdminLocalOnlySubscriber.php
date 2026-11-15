<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Symfony Security protège désormais /admin. Les anciennes routes de vente
 * simulée restent, elles, limitées à dev/test.
 */
final class AdminLocalOnlySubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if ($path !== '/admin' && ! str_starts_with($path, '/admin/')) {
            return;
        }

        if (!in_array($this->environment, ['dev', 'test'], true)
            && preg_match('~/(reservation|simulation/(confirm|fail|cancel)|annulation)$~', $path)) {
            throw new NotFoundHttpException('La simulation est désactivée dans cet environnement.');
        }
    }
}
