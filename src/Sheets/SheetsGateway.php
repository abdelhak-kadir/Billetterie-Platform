<?php

declare(strict_types=1);
namespace App\Sheets;
interface SheetsGateway
{
    public function target(): string;
    /** Écriture sur une ligne stable, jamais append. @param list<string|int> $values */
    public function write(int $row, array $values): void;
}
