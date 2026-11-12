<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ZoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZoneRepository::class)]
#[ORM\Table(name: 'zone')]
#[ORM\UniqueConstraint(name: 'uniq_zone_salle_nom', columns: ['salle_id', 'nom'])]
class Zone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Salle::class, inversedBy: 'zones')]
    #[ORM\JoinColumn(name: 'salle_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Salle $salle;

    #[ORM\Column(length: 100)]
    private string $nom;

    /** @var Collection<int, Place> */
    #[ORM\OneToMany(mappedBy: 'zone', targetEntity: Place::class)]
    #[ORM\OrderBy(['rangee' => 'ASC', 'numero' => 'ASC'])]
    private Collection $places;

    public function __construct(Salle $salle, string $nom)
    {
        $nom = trim($nom);

        if ($nom === '') {
            throw new \InvalidArgumentException('Le nom de la zone est obligatoire.');
        }

        $this->salle = $salle;
        $this->nom = $nom;
        $this->places = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSalle(): Salle
    {
        return $this->salle;
    }

    public function setSalle(Salle $salle): self
    {
        $this->salle = $salle;

        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function renommer(string $nom): void
    {
        $nom = trim($nom);

        if ($nom === '') {
            throw new \InvalidArgumentException('Le nom de la zone est obligatoire.');
        }

        $this->nom = $nom;
    }

    /** @return Collection<int, Place> */
    public function getPlaces(): Collection
    {
        return $this->places;
    }

    public function addPlace(Place $place): self
    {
        if (! $this->places->contains($place)) {
            $this->places->add($place);
            $place->setZone($this);
        }

        return $this;
    }
}
