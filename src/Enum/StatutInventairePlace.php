<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutInventairePlace: string
{
    case Disponible = 'disponible';
    case Reservee = 'reservee';
    case Vendue = 'vendue';
    case Bloquee = 'bloquee';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Reservee => 'Réservée',
            self::Vendue => 'Vendue',
            self::Bloquee => 'Bloquée',
        };
    }
}
