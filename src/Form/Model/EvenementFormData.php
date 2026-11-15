<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Evenement;
use App\Enum\TypeEvenement;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class EvenementFormData
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $titre = null;

    #[Assert\Length(max: 5000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $description = null;

    #[Assert\NotNull(message: 'Le type est obligatoire.')]
    public ?TypeEvenement $type = null;

    #[Assert\Image(
        maxSize: '2M', extensions: ['jpg', 'jpeg', 'png', 'webp'],
        maxWidth: 6000, maxHeight: 6000, maxPixels: 20000000,
        maxSizeMessage: 'Le visuel doit peser au maximum 2 Mo.',
        extensionsMessage: 'Choisissez une image JPG, PNG ou WebP valide.',
        mimeTypesMessage: 'Choisissez une image JPG, PNG ou WebP valide.',
        sizeNotDetectedMessage: 'Ce fichier ne peut pas être lu comme une image.',
        maxWidthMessage: 'La largeur du visuel ne doit pas dépasser 6 000 pixels.',
        maxHeightMessage: 'La hauteur du visuel ne doit pas dépasser 6 000 pixels.',
        maxPixelsMessage: 'Le visuel ne doit pas dépasser 20 millions de pixels.',
        uploadIniSizeErrorMessage: 'Le visuel doit peser au maximum 2 Mo.',
    )]
    public ?UploadedFile $image = null;

    public static function fromEntity(Evenement $evenement): self
    {
        $data = new self();
        $data->titre = $evenement->getTitre();
        $data->description = $evenement->getDescription();
        $data->type = $evenement->getType();

        return $data;
    }
}
