<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Evenement;
use App\Enum\TypeEvenement;
use Symfony\Component\Validator\Constraints as Assert;

final class EvenementFormData
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $titre = null;

    #[Assert\Length(max: 5000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $description = null;

    #[Assert\NotNull(message: 'Le type est obligatoire.')]
    public ?TypeEvenement $type = null;

    public static function fromEntity(Evenement $evenement): self
    {
        $data = new self();
        $data->titre = $evenement->getTitre();
        $data->description = $evenement->getDescription();
        $data->type = $evenement->getType();

        return $data;
    }
}
