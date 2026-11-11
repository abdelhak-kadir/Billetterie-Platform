<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeEvenement: string
{
    case Concert = 'concert';
    case Theatre = 'theatre';
    case Conference = 'conference';

    public function label(): string
    {
        return match ($this) {
            self::Concert => 'Concert',
            self::Theatre => 'Théâtre',
            self::Conference => 'Conférence',
        };
    }
}
