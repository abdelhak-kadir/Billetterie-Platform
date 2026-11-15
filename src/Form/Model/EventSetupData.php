<?php
declare(strict_types=1);
namespace App\Form\Model;
use App\Entity\Salle;
use Symfony\Component\Validator\Constraints as Assert;

final class EventSetupData
{
    #[Assert\Valid]
    public EvenementFormData $evenement;
    #[Assert\NotNull(message: 'Choisissez la salle.')]
    public ?Salle $hall = null;
    #[Assert\Valid, Assert\Count(min: 1, max: 12, minMessage: 'Ajoutez au moins un spectacle.', maxMessage: 'Limitez la création à 12 spectacles par enregistrement.')]
    public array $shows = [];
    public array $rowCategories = [];
    public function __construct() { $this->evenement = new EvenementFormData(); $this->shows = [new ShowSetupData()]; }
}
