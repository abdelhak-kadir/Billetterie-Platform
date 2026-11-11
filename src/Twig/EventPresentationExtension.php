<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Evenement;
use App\Entity\Representation;
use App\Enum\StatutRepresentation;
use App\Repository\RepresentationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Correspondance éditoriale locale, indépendante des identifiants SQL des fixtures. */
final class EventPresentationExtension extends AbstractExtension
{
    private const ARTWORK = [
        'Concert Horizon' => 'concert.webp',
        'Les voix de la scène' => 'theatre.webp',
        'Rencontres des savoirs' => 'conference.webp',
    ];

    public function __construct(private readonly RepresentationRepository $representations)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('event_art', $this->artwork(...)),
            new TwigFunction('next_representation', $this->nextRepresentation(...)),
        ];
    }

    public function artwork(Evenement $evenement): string
    {
        return '/admin/media/'.(self::ARTWORK[$evenement->getTitre()] ?? 'fallback.svg');
    }

    public function nextRepresentation(Evenement $evenement): ?Representation
    {
        return $this->representations->createQueryBuilder('r')
            ->andWhere('r.evenement = :event AND r.statut = :status AND r.debut >= :now')
            ->setParameter('event', $evenement)
            ->setParameter('status', StatutRepresentation::Programme)
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->orderBy('r.debut', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
