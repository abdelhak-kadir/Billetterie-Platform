<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Entity\Salle;
use App\Repository\PlaceRepository;
use App\Repository\SalleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/salles', name: 'admin_salle_')]
final class SalleController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SalleRepository $salles): Response
    {
        $pagination = new Pagination($request->query->getInt('page', 1), 20, $salles->count([]));

        $items = $salles->createQueryBuilder('s')
            ->orderBy('s.nom', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        return $this->render('admin/salle/index.html.twig', [
            'salles' => $items,
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
            ->orderBy('z.nom', 'ASC')
            ->addOrderBy('p.rangee', 'ASC')
            ->addOrderBy('p.numero', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        return $this->render('admin/salle/show.html.twig', [
            'salle' => $salle,
            'zones' => $salle->getZones(),
            'places' => $placesPage,
            'pagination' => $pagination,
        ]);
    }
}
