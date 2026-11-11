<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\EventSubscriber\AdminLocalOnlySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminLocalOnlySubscriberTest extends TestCase
{
    public function testAdminIsBlockedInProduction(): void
    {
        $event = $this->requestEvent('/admin/evenements');
        $subscriber = new AdminLocalOnlySubscriber('prod');

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testAdminIsAllowedInDevelopmentAndTest(): void
    {
        $subscriber = new AdminLocalOnlySubscriber('dev');
        $subscriber->onKernelRequest($this->requestEvent('/admin/evenements'));

        $subscriber = new AdminLocalOnlySubscriber('test');
        $subscriber->onKernelRequest($this->requestEvent('/admin'));

        self::assertTrue(true);
    }

    public function testNonAdminPathsAreIgnored(): void
    {
        $subscriber = new AdminLocalOnlySubscriber('prod');
        $subscriber->onKernelRequest($this->requestEvent('/'));

        self::assertTrue(true);
    }

    private function requestEvent(string $path): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(KernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
