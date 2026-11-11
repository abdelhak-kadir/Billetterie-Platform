<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\Evenement;
use App\Entity\InventairePlace;
use App\Entity\Representation;
use App\Entity\Salle;
use App\Entity\Tarif;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'counts' => [
                'salles' => $entityManager->getRepository(Salle::class)->count([]),
                'evenements' => $entityManager->getRepository(Evenement::class)->count([]),
                'representations' => $entityManager->getRepository(Representation::class)->count([]),
                'inventaires' => $entityManager->getRepository(InventairePlace::class)->count([]),
                'tarifs' => $entityManager->getRepository(Tarif::class)->count([]),
                'commandes' => $entityManager->getRepository(Commande::class)->count([]),
                'billets' => $entityManager->getRepository(Billet::class)->count([]),
            ],
        ]);
    }
}
