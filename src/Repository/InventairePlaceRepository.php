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

    /**
     * @param list<int> $representationIds
     *
     * @return array<int, array<string, int>> compteurs par représentation et par statut
     */
    public function countsByRepresentationIds(array $representationIds): array
    {
        if ($representationIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.representation) AS representationId', 'i.statut AS statut', 'COUNT(i.id) AS total')
            ->andWhere('i.representation IN (:ids)')
            ->setParameter('ids', $representationIds)
            ->groupBy('i.representation')
            ->addGroupBy('i.statut')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $statut = $row['statut'];
            $statusKey = $statut instanceof \BackedEnum ? $statut->value : (string) $statut;
            $counts[(int) $row['representationId']][$statusKey] = (int) $row['total'];
        }

        return $counts;
    }
}
