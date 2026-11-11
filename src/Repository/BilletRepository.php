<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Billet;
use App\Entity\InventairePlace;
use App\Enum\StatutBillet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Billet> */
class BilletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Billet::class);
    }

    public function findOneValideForInventaire(InventairePlace $inventairePlace): ?Billet
    {
        return $this->findOneBy([
            'inventairePlace' => $inventairePlace,
            'statut' => StatutBillet::Valide,
        ]);
    }
}
