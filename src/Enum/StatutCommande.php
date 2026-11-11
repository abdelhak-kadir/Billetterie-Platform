<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutCommande: string
{
    case EnAttente = 'en_attente';
    case Payee = 'payee';
    case Echouee = 'echouee';
    case Expiree = 'expiree';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Payee => 'Payée',
            self::Echouee => 'Échouée',
            self::Expiree => 'Expirée',
            self::Annulee => 'Annulée',
        };
    }
}
