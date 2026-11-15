<?php
declare(strict_types=1);
namespace App\Form\Model;
use Symfony\Component\Validator\Constraints as Assert;

final class ShowSetupData
{
    #[Assert\NotBlank(message: 'Donnez un nom au spectacle.'), Assert\Length(max: 150)]
    public ?string $nom = null;
    #[Assert\NotNull(message: 'Choisissez la date et l’heure du spectacle.')]
    #[Assert\GreaterThan('now', message: 'Choisissez une date future pour ouvrir les ventes.')]
    public ?\DateTimeImmutable $debut = null;
    #[Assert\NotNull(message: 'Indiquez le prix.'), Assert\PositiveOrZero(message: 'Le prix doit être positif ou nul.')]
    public ?int $standardPrice = 10000;
    #[Assert\NotNull(message: 'Indiquez le prix.'), Assert\PositiveOrZero(message: 'Le prix doit être positif ou nul.')]
    public ?int $vipPrice = 20000;
    #[Assert\NotNull(message: 'Indiquez le prix.'), Assert\PositiveOrZero(message: 'Le prix doit être positif ou nul.')]
    public ?int $vvipPrice = 30000;
}
