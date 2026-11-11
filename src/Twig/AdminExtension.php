<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AdminExtension extends AbstractExtension
{
    private const DISPLAY_TIMEZONE = 'Africa/Casablanca';

    public function getFilters(): array
    {
        return [
            new TwigFilter('mad', $this->formatMad(...)),
            new TwigFilter('casablanca_date', $this->formatCasablancaDate(...)),
        ];
    }

    public function formatMad(int $centimes): string
    {
        return number_format($centimes / 100, 2, ',', ' ').' MAD';
    }

    public function formatCasablancaDate(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        return \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone(self::DISPLAY_TIMEZONE))
            ->format('d/m/Y H:i');
    }
}
