<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutInventairePlace;
use App\Repository\InventairePlaceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventairePlaceRepository::class)]
#[ORM\Table(name: 'inventaire_place')]
#[ORM\UniqueConstraint(name: 'uniq_inventaire_representation_place', columns: ['representation_id', 'place_id'])]
#[ORM\Index(name: 'idx_inventaire_place_place', columns: ['place_id'])]
#[ORM\Index(name: 'idx_inventaire_commande_reservation', columns: ['commande_reservation_id'])]
class InventairePlace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Representation::class, inversedBy: 'inventairePlaces')]
    #[ORM\JoinColumn(name: 'representation_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Representation $representation;

    #[ORM\ManyToOne(targetEntity: Place::class)]
    #[ORM\JoinColumn(name: 'place_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Place $place;

    #[ORM\Column(length: 20, enumType: StatutInventairePlace::class)]
    private StatutInventairePlace $statut;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'commande_reservation_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Commande $commandeReservation = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reservationExpireA = null;

    public function __construct(Representation $representation, Place $place)
    {
        if (! self::appartientALaSalle($place, $representation->getSalle())) {
            throw new \InvalidArgumentException('La place n’appartient pas à la salle de la représentation.');
        }

        $this->representation = $representation;
        $this->place = $place;
        $this->statut = StatutInventairePlace::Disponible;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRepresentation(): Representation
    {
        return $this->representation;
    }

    public function getPlace(): Place
    {
        return $this->place;
    }

    public function getStatut(): StatutInventairePlace
    {
        return $this->statut;
    }

    public function getCommandeReservation(): ?Commande
    {
        return $this->commandeReservation;
    }

    public function getReservationExpireA(): ?\DateTimeImmutable
    {
        return $this->reservationExpireA;
    }

    public function reserver(Commande $commande, \DateTimeImmutable $expireA): void
    {
        if ($this->statut !== StatutInventairePlace::Disponible) {
            throw new \LogicException('Seule une place disponible peut être réservée.');
        }

        $this->statut = StatutInventairePlace::Reservee;
        $this->commandeReservation = $commande;
        $this->reservationExpireA = $expireA->setTimezone(new \DateTimeZone('UTC'));
    }

    public function liberer(): void
    {
        if ($this->statut !== StatutInventairePlace::Reservee) {
            throw new \LogicException('Seule une place réservée peut être libérée.');
        }

        $this->statut = StatutInventairePlace::Disponible;
        $this->commandeReservation = null;
        $this->reservationExpireA = null;
    }

    public function vendre(): void
    {
        if ($this->statut !== StatutInventairePlace::Reservee) {
            throw new \LogicException('Seule une place réservée peut être vendue.');
        }

        $this->statut = StatutInventairePlace::Vendue;
        $this->commandeReservation = null;
        $this->reservationExpireA = null;
    }

    public function bloquer(): void
    {
        $this->statut = StatutInventairePlace::Bloquee;
        $this->commandeReservation = null;
        $this->reservationExpireA = null;
    }

    public static function appartientALaSalle(Place $place, Salle $salle): bool
    {
        $salleDeLaPlace = $place->getZone()->getSalle();

        if ($salle->getId() !== null && $salleDeLaPlace->getId() !== null) {
            return $salle->getId() === $salleDeLaPlace->getId();
        }

        return $salle === $salleDeLaPlace;
    }
}
