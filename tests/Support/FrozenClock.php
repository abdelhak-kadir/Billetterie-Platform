<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Clock\Clock;

final class FrozenClock implements Clock
{
    public function __construct(public \DateTimeImmutable $instant) {}
    public function now(): \DateTimeImmutable { return $this->instant; }
    public function advance(string $interval): void { $this->instant = $this->instant->modify($interval); }
}
