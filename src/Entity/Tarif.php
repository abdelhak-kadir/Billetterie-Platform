<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CategorieTarif;
use App\Repository\TarifRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifRepository::class)]
#[ORM\Table(name: 'tarif')]
#[ORM\Index(name: 'IDX_TARIFF_CATEGORY', columns: ['seat_category_id'])]
#[ORM\UniqueConstraint(name: 'uniq_tarif_representation_libelle', columns: ['representation_id', 'libelle'])]
class Tarif
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Representation::class, inversedBy: 'tarifs')]
    #[ORM\JoinColumn(name: 'representation_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Representation $representation;

    #[ORM\Column(length: 100)]
    private string $libelle;

    #[ORM\Column(length: 20, enumType: CategorieTarif::class)]
    private CategorieTarif $categorie;

    #[ORM\Column]
    private int $prixCentimes;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\ManyToOne(targetEntity: SeatCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?SeatCategory $seatCategory = null;

    public function getSeatCategory(): ?SeatCategory { return $this->seatCategory; }
    public function setSeatCategory(?SeatCategory $category): void
    {
        if ($category !== null && $category->getRepresentation() !== $this->representation
            && ($category->getRepresentation()->getId() === null || $category->getRepresentation()->getId() !== $this->representation->getId())) {
            throw new \InvalidArgumentException('La catégorie appartient à un autre spectacle.');
        }
        $this->seatCategory = $category;
    }
    public function appliesTo(InventairePlace $inventory): bool
    {
        return $this->seatCategory === $inventory->getSeatCategory()
            || ($this->seatCategory !== null && $inventory->getSeatCategory() !== null
                && $this->seatCategory->getId() !== null && $this->seatCategory->getId() === $inventory->getSeatCategory()->getId());
    }

    public function __construct(Representation $representation, string $libelle, CategorieTarif $categorie, int $prixCentimes)
    {
        $libelle = trim($libelle);

        if ($libelle === '') {
            throw new \InvalidArgumentException('Le libellé du tarif est obligatoire.');
        }

        if ($prixCentimes < 0) {
            throw new \InvalidArgumentException('Le prix d’un tarif ne peut pas être négatif.');
        }

        $this->representation = $representation;
        $this->libelle = $libelle;
        $this->categorie = $categorie;
        $this->prixCentimes = $prixCentimes;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRepresentation(): Representation
    {
        return $this->representation;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getCategorie(): CategorieTarif
    {
        return $this->categorie;
    }

    public function getPrixCentimes(): int
    {
        return $this->prixCentimes;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function changerPrix(int $prixCentimes): void
    {
        if ($prixCentimes < 0) {
            throw new \InvalidArgumentException('Le prix d’un tarif ne peut pas être négatif.');
        }

        $this->prixCentimes = $prixCentimes;
    }

    public function desactiver(): void
    {
        $this->actif = false;
    }

    public function activer(): void
    {
        $this->actif = true;
    }

    public function modifier(Representation $representation, string $libelle, CategorieTarif $categorie, int $prixCentimes): void
    {
        $libelle = trim($libelle);

        if ($libelle === '') {
            throw new \InvalidArgumentException('Le libellé du tarif est obligatoire.');
        }

        if ($prixCentimes < 0) {
            throw new \InvalidArgumentException('Le prix d’un tarif ne peut pas être négatif.');
        }

        $this->representation = $representation;
        $this->libelle = $libelle;
        $this->categorie = $categorie;
        $this->prixCentimes = $prixCentimes;
    }
}
