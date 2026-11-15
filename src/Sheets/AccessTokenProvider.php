<?php

declare(strict_types=1);
namespace App\Sheets;
interface AccessTokenProvider
{
    public function token(): string;
    public function invalidate(): void;
}
