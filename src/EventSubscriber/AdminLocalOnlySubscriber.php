<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Le back-office n'a pas encore d'authentification (Niveau 3).
 * Il reste donc limité aux environnements de développement et de test.
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

        if ($this->environment === 'prod') {
            throw new NotFoundHttpException('Le back-office local n’est pas disponible en production sans authentification.');
        }
    }
}
