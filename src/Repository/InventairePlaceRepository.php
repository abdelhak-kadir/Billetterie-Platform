<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InventairePlace;
use App\Entity\Place;
use App\Entity\Representation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventairePlace> */
class InventairePlaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventairePlace::class);
    }

    public function countForRepresentation(Representation $representation): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.representation = :representation')
            ->setParameter('representation', $representation)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForRepresentationAndPlace(Representation $representation, Place $place): ?InventairePlace
    {
        return $this->findOneBy([
            'representation' => $representation,
            'place' => $place,
        ]);
    }
}
