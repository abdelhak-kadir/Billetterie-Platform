<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Evenement, Salle, Tarif};
use App\Enum\{CategorieTarif, TypeEvenement};
use App\Form\Model\{EventSetupData, ShowSetupData};
use Doctrine\ORM\EntityManagerInterface;

/** Même création que l'administration ; programmation fictive versionnée et rejouable. */
class DemoProgram
{
    public const CONCERT_KEY = 'horizon.demo.v2.concert';
    public const TITLE = 'Horizon Live';
    public const OPENING = 'Première partie';
    public const HEADLINER = 'Concert principal';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventSetupService $setup,
    ) {}

    public function create(string $program, Salle $hall): Evenement
    {
        $definitions = [
            'concert' => [self::TITLE, TypeEvenement::Concert,
                'Une même soirée, deux spectacles à choisir : première partie puis concert principal. Démonstration fictive.',
                [[self::OPENING, '2026-12-01T19:00:00Z', 10000], [self::HEADLINER, '2026-12-01T20:30:00Z', 12000]]],
            'theatre' => ['Les voix de la scène', TypeEvenement::Theatre,
                'Une pièce de théâtre fictive pour découvrir le parcours de réservation.',
                [['Les voix de la scène — Le spectacle', '2026-12-03T19:00:00Z', 10000]]],
            'conference' => ['Rencontres des savoirs', TypeEvenement::Conference,
                'Une conférence fictive avec places et billets individuels.',
                [['Conférence — Regards sur demain', '2026-12-04T17:00:00Z', 10000]]],
        ];
        [$title, $type, $description, $shows] = $definitions[$program]
            ?? throw new \InvalidArgumentException('Programme fictif inconnu.');
        $key = 'horizon.demo.v2.'.$program;
        $existing = $this->em->getRepository(Evenement::class)->findOneBy(['demoKey' => $key]);
        if ($existing !== null) {
            // Conserver aussi les éventuelles modifications faites ensuite par l'administrateur.
            return $existing;
        }

        return $this->em->wrapInTransaction(function () use ($hall, $title, $type, $description, $shows, $key): Evenement {
            $data = new EventSetupData();
            $data->evenement->titre = $title;
            $data->evenement->type = $type;
            $data->evenement->description = $description;
            $data->hall = $hall;
            $data->shows = [];
            foreach ($shows as [$name, $date, $price]) {
                $show = new ShowSetupData();
                $show->nom = $name;
                $show->debut = new \DateTimeImmutable($date);
                $show->standardPrice = $price;
                $show->vipPrice = 2 * $price;
                $show->vvipPrice = 3 * $price;
                $data->shows[] = $show;
            }
            $mainZone = null;
            foreach ($hall->getZones() as $zone) {
                if ($mainZone === null || $zone->getPlaces()->count() > $mainZone->getPlaces()->count()) {
                    $mainZone = $zone;
                }
            }
            foreach ($hall->getPlaces() as $place) {
                $code = 'standard';
                if ($place->getZone() === $mainZone) {
                    $code = in_array($place->getRangee(), ['A', 'B'], true) ? 'vvip'
                        : (in_array($place->getRangee(), ['C', 'D', 'E'], true) ? 'vip' : 'standard');
                }
                $data->rowCategories[EventSetupService::rowKey($place->getZone()->getId(), $place->getRangee())] = $code;
            }
            $event = $this->setup->create($data);
            $event->markAsDemo($key);
            foreach ($event->getRepresentations() as $show) {
                $fullPrices = $show->getTarifs()->toArray();
                foreach ($fullPrices as $full) {
                    foreach ([[CategorieTarif::Reduit, 80], [CategorieTarif::Groupe, 60], [CategorieTarif::Invitation, 0]] as [$kind, $percent]) {
                        $tariff = new Tarif($show, $full->getSeatCategory()->getNom().' · '.$kind->label(), $kind,
                            intdiv($full->getPrixCentimes() * $percent, 100));
                        $tariff->setSeatCategory($full->getSeatCategory());
                        $show->addTarif($tariff);
                        $this->em->persist($tariff);
                    }
                }
            }
            $this->em->flush();

            return $event;
        });
    }
}
