<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Entity\Commande;
use App\Enum\CanalCommande;
use App\Enum\StatutCommande;
use App\Repository\BilletRepository;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/commandes', name: 'admin_commande_')]
final class CommandeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, CommandeRepository $commandes): Response
    {
        $queryBuilder = $commandes->createQueryBuilder('c');

        $query = trim($request->query->getString('q', ''));
        if ($query !== '') {
            $queryBuilder->andWhere('c.reference LIKE :q OR c.emailClient LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        $canal = CanalCommande::tryFrom($request->query->getString('canal', ''));
        if ($canal !== null) {
            $queryBuilder->andWhere('c.canal = :canal')->setParameter('canal', $canal);
        }

        $statut = StatutCommande::tryFrom($request->query->getString('statut', ''));
        if ($statut !== null) {
            $queryBuilder->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pagination = new Pagination($request->query->getInt('page', 1), 20, $total);

        $items = $queryBuilder
            ->orderBy('c.creeA', 'DESC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        return $this->render('admin/commande/index.html.twig', [
            'commandes' => $items,
            'pagination' => $pagination,
            'q' => $query,
            'canal' => $canal,
            'statut' => $statut,
            'canaux' => CanalCommande::cases(),
            'statuts' => StatutCommande::cases(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, CommandeRepository $commandes, BilletRepository $billets): Response
    {
        $commande = $commandes->find($id) ?? throw $this->createNotFoundException('Commande introuvable.');

        return $this->render('admin/commande/show.html.twig', [
            'commande' => $commande,
            'billets' => $billets->findByCommandeWithDetails($commande),
            'reservationItems' => $commande->getReservationItems(),
            'refundTotal' => array_sum(array_map(static fn ($b) => $b->getMontantRembourseCentimes(), $billets->findByCommandeWithDetails($commande))),
        ]);
    }
}
