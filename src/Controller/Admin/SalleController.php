<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Entity\Salle;
use App\Entity\Zone;
use App\Repository\PlaceRepository;
use App\Repository\SalleRepository;
use App\Repository\ZoneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/salles', name: 'admin_salle_')]
final class SalleController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        SalleRepository $salles,
        PlaceRepository $places,
        ZoneRepository $zones,
    ): Response {
        $pagination = new Pagination($request->query->getInt('page', 1), 20, $salles->count([]));

        $items = $salles->createQueryBuilder('s')
            ->orderBy('s.nom', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        $salleIds = array_map(static fn (Salle $salle): int => (int) $salle->getId(), $items);

        return $this->render('admin/salle/index.html.twig', [
            'salles' => $items,
            'placeCounts' => $places->countBySalleIds($salleIds),
            'zoneCounts' => $zones->countBySalleIds($salleIds),
            'pagination' => $pagination,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request, SalleRepository $salles, PlaceRepository $places): Response
    {
        $salle = $salles->find($id) ?? throw $this->createNotFoundException('Salle introuvable.');

        $queryBuilder = $places->createQueryBuilder('p')
            ->join('p.zone', 'z')
            ->addSelect('z')
            ->andWhere('z.salle = :salle')
            ->setParameter('salle', $salle);

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pagination = new Pagination($request->query->getInt('page', 1), 50, $total);

        $placesPage = $queryBuilder
            ->orderBy('z.id', 'ASC')
            ->addOrderBy('p.rangee', 'ASC')
            ->addOrderBy('p.numero', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        $zones = $salle->getZones();
        $zoneCounts = $places->countByZoneIds(
            $zones->map(static fn (Zone $zone): int => (int) $zone->getId())->toArray(),
        );

        return $this->render('admin/salle/show.html.twig', [
            'salle' => $salle,
            'zones' => $zones,
            'zoneCounts' => $zoneCounts,
            'places' => $placesPage,
            'pagination' => $pagination,
        ]);
    }
}
