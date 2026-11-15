<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutBillet: string
{
    case Valide = 'valide';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Valide => 'Valide',
            self::Annule => 'Annulé',
        };
    }
}
