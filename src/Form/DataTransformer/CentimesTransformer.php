<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Convertit un montant saisi en MAD (2 décimales) vers un entier de centimes.
 * Le prix payé des billets n'est jamais concerné par ce formulaire.
 *
 * @implements DataTransformerInterface<int, string>
 */
final class CentimesTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_int($value) && ! is_numeric($value)) {
            throw new TransformationFailedException('Le montant en centimes est invalide.');
        }

        return number_format(((int) $value) / 100, 2, '.', '');
    }

    public function reverseTransform(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new TransformationFailedException('Le montant saisi n’est pas un nombre valide.');
        }

        return (int) round(((float) $value) * 100);
    }
}
