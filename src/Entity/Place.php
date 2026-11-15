<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlaceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
#[ORM\Table(name: 'place')]
#[ORM\UniqueConstraint(name: 'uniq_place_zone_rangee_numero', columns: ['zone_id', 'rangee', 'numero'])]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Zone::class, inversedBy: 'places')]
    #[ORM\JoinColumn(name: 'zone_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Zone $zone;

    #[ORM\Column(length: 20)]
    private string $rangee;

    #[ORM\Column]
    private int $numero;

    public function __construct(Zone $zone, string $rangee, int $numero)
    {
        $rangee = trim($rangee);

        if ($rangee === '') {
            throw new \InvalidArgumentException('La rangée de la place est obligatoire.');
        }

        if ($numero < 1) {
            throw new \InvalidArgumentException('Le numéro de la place doit être strictement positif.');
        }

        $this->zone = $zone;
        $this->rangee = $rangee;
        $this->numero = $numero;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZone(): Zone
    {
        return $this->zone;
    }

    public function setZone(Zone $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    public function getRangee(): string
    {
        return $this->rangee;
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function getLibelle(): string
    {
        return sprintf('%s · Rangée %s · Place %d', $this->zone->getNom(), $this->rangee, $this->numero);
    }
}
