<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CategorieTarif;
use App\Repository\TarifRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifRepository::class)]
#[ORM\Table(name: 'tarif')]
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
}
