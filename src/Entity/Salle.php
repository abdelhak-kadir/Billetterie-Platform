<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SalleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalleRepository::class)]
#[ORM\Table(name: 'salle')]
class Salle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $nom;

    /** @var Collection<int, Zone> */
    #[ORM\OneToMany(mappedBy: 'salle', targetEntity: Zone::class)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $zones;

    /** @var Collection<int, Representation> */
    #[ORM\OneToMany(mappedBy: 'salle', targetEntity: Representation::class)]
    private Collection $representations;

    public function __construct(string $nom)
    {
        $nom = trim($nom);

        if ($nom === '') {
            throw new \InvalidArgumentException('Le nom de la salle est obligatoire.');
        }

        $this->nom = $nom;
        $this->zones = new ArrayCollection();
        $this->representations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function renommer(string $nom): void
    {
        $nom = trim($nom);

        if ($nom === '') {
            throw new \InvalidArgumentException('Le nom de la salle est obligatoire.');
        }

        $this->nom = $nom;
    }

    /** @return Collection<int, Zone> */
    public function getZones(): Collection
    {
        return $this->zones;
    }

    public function addZone(Zone $zone): self
    {
        if (! $this->zones->contains($zone)) {
            $this->zones->add($zone);
            $zone->setSalle($this);
        }

        return $this;
    }

    /** @return Collection<int, Representation> */
    public function getRepresentations(): Collection
    {
        return $this->representations;
    }

    public function addRepresentation(Representation $representation): self
    {
        if (! $this->representations->contains($representation)) {
            $this->representations->add($representation);
        }

        return $this;
    }

    /**
     * La capacité est dérivée du plan physique : elle n'est pas stockée.
     */
    public function getCapacite(): int
    {
        $capacite = 0;

        foreach ($this->getPlaces() as $place) {
            ++$capacite;
        }

        return $capacite;
    }

    /** @return iterable<Place> */
    public function getPlaces(): iterable
    {
        foreach ($this->zones as $zone) {
            foreach ($zone->getPlaces() as $place) {
                yield $place;
            }
        }
    }
}
