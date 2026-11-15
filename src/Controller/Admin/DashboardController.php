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
use App\Enum\StatutRepresentation;
use App\Repository\InventairePlaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager, InventairePlaceRepository $inventaires): Response
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $representations = $entityManager->getRepository(Representation::class)->createQueryBuilder('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->join('r.salle', 's')->addSelect('s')
            ->andWhere('r.debut >= :now')
            ->andWhere('r.statut = :status')
            ->setParameter('now', $now)
            ->setParameter('status', StatutRepresentation::Programme)
            ->orderBy('r.debut', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $evenements = $entityManager->getRepository(Evenement::class)->findBy([], ['titre' => 'ASC'], 3);
        $firstRepresentation = $representations[0] ?? null;
        $featured = $firstRepresentation?->getEvenement() ?? ($evenements[0] ?? null);
        $next = $firstRepresentation;

        return $this->render('admin/dashboard.html.twig', [
            'featured' => $featured,
            'next' => $next,
            'evenements' => $evenements,
            'representations' => $representations,
            'inventoryCounts' => $inventaires->countsByRepresentationIds(array_map(
                static fn (Representation $r): int => (int) $r->getId(), $representations,
            )),
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
