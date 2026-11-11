<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\InventairePlace;
use App\Entity\Place;
use App\Entity\Representation;
use App\Entity\Salle;
use App\Enum\StatutRepresentation;
use App\Repository\InventairePlaceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée une représentation et l'inventaire complet de sa salle dans une seule
 * transaction. Le nombre de lignes est dérivé du plan physique : aucune
 * capacité fixe n'est codée en dur.
 */
class RepresentationInventoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventairePlaceRepository $inventairePlaceRepository,
    ) {
    }

    public function createRepresentation(
        Evenement $evenement,
        Salle $salle,
        \DateTimeImmutable $debut,
        StatutRepresentation $statut = StatutRepresentation::Programme,
    ): Representation {
        return $this->entityManager->wrapInTransaction(function () use ($evenement, $salle, $debut, $statut): Representation {
            $places = $this->placesDeLaSalle($salle);

            if ($places === []) {
                throw new \DomainException('Impossible de créer une représentation pour une salle sans place.');
            }

            $representation = new Representation($evenement, $salle, $debut, $statut);
            $this->entityManager->persist($representation);

            foreach ($places as $place) {
                $inventairePlace = $this->createInventoryPlace($representation, $place);
                $this->entityManager->persist($inventairePlace);
                $representation->addInventairePlace($inventairePlace);
            }

            $this->entityManager->flush();

            return $representation;
        });
    }

    /**
     * Initialise l'inventaire d'une représentation existante.
     * Une répétition est sans effet : elle retourne le nombre de lignes déjà
     * présentes et n'en crée pas de nouvelles.
     */
    public function initializeInventory(Representation $representation): int
    {
        return $this->entityManager->wrapInTransaction(function () use ($representation): int {
            $places = $this->placesDeLaSalle($representation->getSalle());

            if ($places === []) {
                throw new \DomainException('Impossible d’initialiser l’inventaire d’une salle sans place.');
            }

            $existing = $this->inventairePlaceRepository->countForRepresentation($representation);

            if ($existing === count($places)) {
                return $existing;
            }

            if ($existing > 0) {
                throw new \DomainException('L’inventaire de cette représentation est partiel ; aucune ligne supplémentaire n’a été créée.');
            }

            foreach ($places as $place) {
                $inventairePlace = $this->createInventoryPlace($representation, $place);
                $this->entityManager->persist($inventairePlace);
                $representation->addInventairePlace($inventairePlace);
            }

            $this->entityManager->flush();

            return count($places);
        });
    }

    /**
     * @return list<Place>
     */
    private function placesDeLaSalle(Salle $salle): array
    {
        $places = [];

        foreach ($salle->getZones() as $zone) {
            foreach ($zone->getPlaces() as $place) {
                $places[] = $place;
            }
        }

        return $places;
    }

    protected function createInventoryPlace(Representation $representation, Place $place): InventairePlace
    {
        return new InventairePlace($representation, $place);
    }
}
