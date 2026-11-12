<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Ligne immuable : conserve l'offre acceptée même après expiration ou annulation. */
#[ORM\Entity]
#[ORM\Table(name: 'reservation_item')]
#[ORM\UniqueConstraint(name: 'uniq_reservation_order_inventory', columns: ['commande_id', 'inventaire_place_id'])]
class ReservationItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'reservationItems')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private Commande $commande,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private InventairePlace $inventairePlace,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private Tarif $tarif,
        #[ORM\Column]
        private int $prixCentimes,
    ) {
        if ($prixCentimes < 0 || $inventairePlace->getRepresentation()->getId() !== $tarif->getRepresentation()->getId()) {
            throw new \InvalidArgumentException('Ligne de réservation incohérente.');
        }
    }

    public function getCommande(): Commande { return $this->commande; }
    public function getInventairePlace(): InventairePlace { return $this->inventairePlace; }
    public function getTarif(): Tarif { return $this->tarif; }
    public function getPrixCentimes(): int { return $this->prixCentimes; }
}
