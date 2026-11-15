<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Entity\Billet;
use App\Enum\StatutBillet;
use App\Repository\BilletRepository;
use App\Repository\RepresentationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/billets', name: 'admin_billet_')]
final class BilletController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        BilletRepository $billets,
        RepresentationRepository $representations,
    ): Response {
        $queryBuilder = $billets->createQueryBuilder('b')
            ->join('b.commande', 'c')->addSelect('c')
            ->join('b.inventairePlace', 'i')->addSelect('i')
            ->join('i.place', 'p')->addSelect('p')
            ->join('p.zone', 'z')->addSelect('z')
            ->join('i.representation', 'r')->addSelect('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->join('b.tarif', 't')->addSelect('t');

        $query = trim($request->query->getString('q', ''));
        if ($query !== '') {
            $queryBuilder->andWhere('b.reference LIKE :q OR c.reference LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        $statut = StatutBillet::tryFrom($request->query->getString('statut', ''));
        if ($statut !== null) {
            $queryBuilder->andWhere('b.statut = :statut')->setParameter('statut', $statut);
        }

        $representationId = $request->query->getInt('representation', 0);
        if ($representationId > 0) {
            $queryBuilder->andWhere('r.id = :representation')->setParameter('representation', $representationId);
        }

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pagination = new Pagination($request->query->getInt('page', 1), 20, $total);

        $items = $queryBuilder
            ->orderBy('b.reference', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        $representationChoices = $representations->createQueryBuilder('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->orderBy('r.debut', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/billet/index.html.twig', [
            'billets' => $items,
            'pagination' => $pagination,
            'representations' => $representationChoices,
            'statuts' => StatutBillet::cases(),
            'q' => $query,
            'statut' => $statut,
            'representationId' => $representationId,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, BilletRepository $billets): Response
    {
        $billet = $billets->find($id) ?? throw $this->createNotFoundException('Billet introuvable.');

        return $this->render('admin/billet/show.html.twig', [
            'billet' => $billet,
        ]);
    }
}
