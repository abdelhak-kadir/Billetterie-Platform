<?php

declare(strict_types=1);
namespace App\Security;

use App\Entity\User;
use App\Enum\CategorieTarif;

final class TariffAccess
{
    /** D07 : permissions de démonstration, aucun âge ni seuil de groupe inventé. */
    public static function categories(User $user): array
    {
        return match (true) {
            in_array('ROLE_ADMIN', $user->getRoles(), true) => CategorieTarif::cases(),
            in_array('ROLE_GUICHETIER', $user->getRoles(), true) => [CategorieTarif::Plein, CategorieTarif::Reduit, CategorieTarif::Groupe],
            default => [CategorieTarif::Plein],
        };
    }
}
