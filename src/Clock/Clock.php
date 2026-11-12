<?php

declare(strict_types=1);

namespace App\Clock;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
