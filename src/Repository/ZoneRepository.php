<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Zone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Zone> */
class ZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Zone::class);
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

        $rows = $this->createQueryBuilder('z')
            ->select('IDENTITY(z.salle) AS salleId', 'COUNT(z.id) AS total')
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
}
