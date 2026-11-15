<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Representation;
use App\Entity\Tarif;
use App\Enum\CategorieTarif;
use Symfony\Component\Validator\Constraints as Assert;

final class TarifFormData
{
    #[Assert\NotNull(message: 'La représentation est obligatoire.')]
    public ?Representation $representation = null;

    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $libelle = null;

    #[Assert\NotNull(message: 'La catégorie est obligatoire.')]
    public ?CategorieTarif $categorie = null;

    #[Assert\NotNull(message: 'Le prix est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    public ?int $prixCentimes = null;

    public static function fromEntity(Tarif $tarif): self
    {
        $data = new self();
        $data->representation = $tarif->getRepresentation();
        $data->libelle = $tarif->getLibelle();
        $data->categorie = $tarif->getCategorie();
        $data->prixCentimes = $tarif->getPrixCentimes();

        return $data;
    }
}
