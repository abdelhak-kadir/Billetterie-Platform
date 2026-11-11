<?php

declare(strict_types=1);

namespace App\Enum;

enum CategorieTarif: string
{
    case Plein = 'plein';
    case Reduit = 'reduit';
    case Groupe = 'groupe';
    case Invitation = 'invitation';

    public function label(): string
    {
        return match ($this) {
            self::Plein => 'Plein tarif',
            self::Reduit => 'Réduit',
            self::Groupe => 'Groupe',
            self::Invitation => 'Invitation',
        };
    }
}
