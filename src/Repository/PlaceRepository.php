<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Place;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Place> */
class PlaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Place::class);
    }

    /**
     * @param list<int> $salleIds
     *
     * @return array<int, int>
     */
    public function countBySalleIds(array $salleIds): array
    {
        if ($salleIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(z.salle) AS salleId', 'COUNT(p.id) AS total')
            ->join('p.zone', 'z')
            ->andWhere('z.salle IN (:ids)')
            ->setParameter('ids', $salleIds)
            ->groupBy('z.salle')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['salleId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<int> $zoneIds
     *
     * @return array<int, int>
     */
    public function countByZoneIds(array $zoneIds): array
    {
        if ($zoneIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.zone) AS zoneId', 'COUNT(p.id) AS total')
            ->andWhere('p.zone IN (:ids)')
            ->setParameter('ids', $zoneIds)
            ->groupBy('p.zone')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['zoneId']] = (int) $row['total'];
        }

        return $counts;
    }
}
