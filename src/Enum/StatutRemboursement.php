<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutRemboursement: string
{
    case SansObjet = 'sans_objet';
    case ARembourser = 'a_rembourser';
    case Rembourse = 'rembourse';

    public function label(): string
    {
        return match ($this) {
            self::SansObjet => 'Sans objet',
            self::ARembourser => 'À rembourser',
            self::Rembourse => 'Remboursé',
        };
    }
}
