<?php

declare(strict_types=1);
namespace App\Service;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
final class DemoMode
{
    public function __construct(#[Autowire('%kernel.environment%')] private readonly string $environment) {}
    public function enabled(): bool { return in_array($this->environment, ['dev', 'test'], true); }
    public function assertEnabled(): void { if (!$this->enabled()) { throw new NotFoundHttpException('La simulation est désactivée dans cet environnement.'); } }
}
