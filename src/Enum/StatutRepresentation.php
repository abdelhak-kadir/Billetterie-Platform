<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutRepresentation: string
{
    case Programme = 'programmee';
    case Annulee = 'annulee';
    case Terminee = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::Programme => 'Programmée',
            self::Annulee => 'Annulée',
            self::Terminee => 'Terminée',
        };
    }

    public function estOuvrableALaVente(): bool
    {
        return $this === self::Programme;
    }
}
