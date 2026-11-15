<?php

declare(strict_types=1);

namespace App\Admin;

use Symfony\Component\HttpFoundation\InputBag;

final class QueryFilters
{
    /** Une option vide ou invalide équivaut à l'absence de filtre. */
    public static function positiveInt(InputBag $query, string $name, int $default = 0): int
    {
        $value = $query->all()[$name] ?? null;
        if (!is_scalar($value)) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: $default;
    }
}
