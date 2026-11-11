<?php

declare(strict_types=1);

namespace App\Enum;

enum CanalCommande: string
{
    case EnLigne = 'en_ligne';
    case Guichet = 'guichet';

    public function label(): string
    {
        return match ($this) {
            self::EnLigne => 'En ligne',
            self::Guichet => 'Guichet',
        };
    }
}
