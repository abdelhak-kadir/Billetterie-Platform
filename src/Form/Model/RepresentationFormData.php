<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Evenement;
use App\Entity\Representation;
use App\Entity\Salle;
use Symfony\Component\Validator\Constraints as Assert;

final class RepresentationFormData
{
    #[Assert\Length(max: 150, maxMessage: 'Le nom du spectacle est limité à 150 caractères.')]
    public ?string $nom = null;
    #[Assert\NotNull(message: 'L’événement est obligatoire.')]
    public ?Evenement $evenement = null;

    #[Assert\NotNull(message: 'La salle est obligatoire.')]
    public ?Salle $salle = null;

    #[Assert\NotNull(message: 'La date et l’heure sont obligatoires.')]
    public ?\DateTimeImmutable $debut = null;

    public static function fromEntity(Representation $representation): self
    {
        $data = new self();
        $data->evenement = $representation->getEvenement();
        $data->salle = $representation->getSalle();
        $data->debut = $representation->getDebut();
        $data->nom = $representation->getNom();

        return $data;
    }
}
