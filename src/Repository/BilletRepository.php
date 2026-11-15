<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\InventairePlace;
use App\Entity\Tarif;
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

    public function countByTarif(Tarif $tarif): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.tarif = :tarif')
            ->setParameter('tarif', $tarif)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Billet> */
    public function findByCommandeWithDetails(Commande $commande): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.inventairePlace', 'i')->addSelect('i')
            ->join('i.place', 'p')->addSelect('p')
            ->join('p.zone', 'z')->addSelect('z')
            ->join('i.representation', 'r')->addSelect('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->join('b.tarif', 't')->addSelect('t')
            ->andWhere('b.commande = :commande')
            ->setParameter('commande', $commande)
            ->orderBy('b.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
