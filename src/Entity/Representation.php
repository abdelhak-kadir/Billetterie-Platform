<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutRepresentation;
use App\Repository\RepresentationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepresentationRepository::class)]
#[ORM\Table(name: 'representation')]
#[ORM\Index(name: 'idx_representation_evenement', columns: ['evenement_id'])]
#[ORM\Index(name: 'idx_representation_salle', columns: ['salle_id'])]
class Representation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'representations')]
    #[ORM\JoinColumn(name: 'evenement_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Evenement $evenement;

    #[ORM\ManyToOne(targetEntity: Salle::class, inversedBy: 'representations')]
    #[ORM\JoinColumn(name: 'salle_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Salle $salle;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $debut;

    #[ORM\Column(length: 20, enumType: StatutRepresentation::class)]
    private StatutRepresentation $statut;

    /** @var Collection<int, InventairePlace> */
    #[ORM\OneToMany(mappedBy: 'representation', targetEntity: InventairePlace::class)]
    private Collection $inventairePlaces;

    /** @var Collection<int, Tarif> */
    #[ORM\OneToMany(mappedBy: 'representation', targetEntity: Tarif::class)]
    private Collection $tarifs;

    public function __construct(
        Evenement $evenement,
        Salle $salle,
        \DateTimeImmutable $debut,
        StatutRepresentation $statut = StatutRepresentation::Programme,
    ) {
        $this->evenement = $evenement;
        $this->salle = $salle;
        $this->debut = $debut->setTimezone(new \DateTimeZone('UTC'));
        $this->statut = $statut;
        $this->inventairePlaces = new ArrayCollection();
        $this->tarifs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): Evenement
    {
        return $this->evenement;
    }

    public function getSalle(): Salle
    {
        return $this->salle;
    }

    public function getDebut(): \DateTimeImmutable
    {
        return $this->debut;
    }

    public function getStatut(): StatutRepresentation
    {
        return $this->statut;
    }

    public function estOuvrableALaVente(): bool
    {
        return $this->statut->estOuvrableALaVente();
    }

    public function annuler(): void
    {
        if ($this->statut === StatutRepresentation::Terminee) {
            throw new \LogicException('Une représentation terminée ne peut pas être annulée.');
        }

        $this->statut = StatutRepresentation::Annulee;
    }

    public function terminer(): void
    {
        if ($this->statut !== StatutRepresentation::Programme) {
            throw new \LogicException('Seule une représentation programmée peut être terminée.');
        }

        $this->statut = StatutRepresentation::Terminee;
    }

    /** @return Collection<int, InventairePlace> */
    public function getInventairePlaces(): Collection
    {
        return $this->inventairePlaces;
    }

    public function addInventairePlace(InventairePlace $inventairePlace): self
    {
        if (! $this->inventairePlaces->contains($inventairePlace)) {
            $this->inventairePlaces->add($inventairePlace);
        }

        return $this;
    }

    /** @return Collection<int, Tarif> */
    public function getTarifs(): Collection
    {
        return $this->tarifs;
    }

    public function addTarif(Tarif $tarif): self
    {
        if (! $this->tarifs->contains($tarif)) {
            $this->tarifs->add($tarif);
        }

        return $this;
    }
}
