<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Presentation\SeatMapView;
use App\Enum\StatutInventairePlace;
use App\Repository\InventairePlaceRepository;
use App\Repository\RepresentationRepository;
use App\Repository\ZoneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/inventaire', name: 'admin_inventaire_')]
final class InventaireController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        InventairePlaceRepository $inventaires,
        RepresentationRepository $representations,
        ZoneRepository $zones,
        SeatMapView $seatMapView,
    ): Response {
        $queryBuilder = $inventaires->createQueryBuilder('i')
            ->join('i.representation', 'r')->addSelect('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->join('i.place', 'p')->addSelect('p')
            ->join('p.zone', 'z')->addSelect('z')
            ->join('z.salle', 'sa')->addSelect('sa');

        $representationId = $request->query->getInt('representation', 0);
        if ($representationId > 0) {
            $queryBuilder->andWhere('r.id = :representation')->setParameter('representation', $representationId);
        }

        $zoneId = $request->query->getInt('zone', 0);
        if ($zoneId > 0) {
            $queryBuilder->andWhere('z.id = :zone')->setParameter('zone', $zoneId);
        }

        $statut = StatutInventairePlace::tryFrom($request->query->getString('statut', ''));
        if ($statut !== null) {
            $queryBuilder->andWhere('i.statut = :statut')->setParameter('statut', $statut);
        }

        $query = trim($request->query->getString('q', ''));
        if ($query !== '') {
            $queryBuilder->andWhere(
                'e.titre LIKE :q OR z.nom LIKE :q OR p.rangee LIKE :q OR CONCAT(p.rangee, p.numero) LIKE :q',
            )->setParameter('q', '%'.$query.'%');
        }

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pagination = new Pagination($request->query->getInt('page', 1), 25, $total);

        $items = $queryBuilder
            ->orderBy('r.debut', 'ASC')
            ->addOrderBy('sa.nom', 'ASC')
            ->addOrderBy('z.id', 'ASC')
            ->addOrderBy('p.rangee', 'ASC')
            ->addOrderBy('p.numero', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        $representationChoices = $representations->createQueryBuilder('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->orderBy('r.debut', 'ASC')
            ->getQuery()
            ->getResult();

        $zoneChoices = $zones->createQueryBuilder('z')
            ->join('z.salle', 's')->addSelect('s')
            ->orderBy('s.nom', 'ASC')
            ->addOrderBy('z.id', 'ASC')
            ->getQuery()
            ->getResult();

        $selectedRepresentation = $representationId > 0 ? $representations->find($representationId) : null;
        $seatMap = $selectedRepresentation ? $seatMapView->build($selectedRepresentation, $zoneId) : null;

        return $this->render('admin/inventaire/index.html.twig', [
            'inventaires' => $items,
            'pagination' => $pagination,
            'representations' => $representationChoices,
            'zones' => $zoneChoices,
            'statuts' => StatutInventairePlace::cases(),
            'representationId' => $representationId,
            'zoneId' => $zoneId,
            'statut' => $statut,
            'q' => $query,
            'selectedRepresentation' => $selectedRepresentation,
            'seatMap' => $seatMap,
        ]);
    }
}
