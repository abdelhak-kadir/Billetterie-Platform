<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeEvenement;
use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 20, enumType: TypeEvenement::class)]
    private TypeEvenement $type;

    /** @var Collection<int, Representation> */
    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Representation::class)]
    private Collection $representations;

    public function __construct(string $titre, TypeEvenement $type, ?string $description = null)
    {
        $titre = trim($titre);

        if ($titre === '') {
            throw new \InvalidArgumentException('Le titre de l’événement est obligatoire.');
        }

        $description = $description !== null ? trim($description) : null;

        $this->titre = $titre;
        $this->type = $type;
        $this->description = $description === '' ? null : $description;
        $this->representations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): TypeEvenement
    {
        return $this->type;
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

    public function modifier(string $titre, TypeEvenement $type, ?string $description = null): void
    {
        $titre = trim($titre);

        if ($titre === '') {
            throw new \InvalidArgumentException('Le titre de l’événement est obligatoire.');
        }

        $description = $description !== null ? trim($description) : null;

        $this->titre = $titre;
        $this->type = $type;
        $this->description = $description === '' ? null : $description;
    }
}
