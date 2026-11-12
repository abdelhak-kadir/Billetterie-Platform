<?php

declare(strict_types=1);
namespace App\Controller\Store;
use App\Clock\Clock;
use App\Entity\{Evenement, Representation, Tarif};
use App\Enum\{CategorieTarif, StatutRepresentation};
use App\Presentation\SeatMapView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogueController extends AbstractController
{
    #[Route('/', name: 'store_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $q = trim($request->query->getString('q'));
        $query = $em->getRepository(Evenement::class)->createQueryBuilder('e')->orderBy('e.titre', 'ASC');
        if ($q !== '') { $query->where('e.titre LIKE :q')->setParameter('q', '%'.$q.'%'); }
        return $this->render('store/catalogue.html.twig', ['events' => $query->getQuery()->getResult(), 'q' => $q]);
    }
    #[Route('/evenements/{id}', name: 'store_event', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function event(int $id, EntityManagerInterface $em): Response
    {
        $event = $em->find(Evenement::class, $id) ?? throw $this->createNotFoundException();
        return $this->render('store/event.html.twig', ['event' => $event, 'representations' => $em->getRepository(Representation::class)->findBy(['evenement' => $event], ['debut' => 'ASC'])]);
    }
    #[Route('/representations/{id}/plan', name: 'store_plan', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/guichet/representations/{id}/plan', name: 'counter_plan', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function plan(int $id, Request $request, EntityManagerInterface $em, SeatMapView $view, Clock $clock): Response
    {
        $rep = $em->find(Representation::class, $id) ?? throw $this->createNotFoundException();
        try { $requested = SeatMapView::parseSelection($request->query->all('seats')); }
        catch (\InvalidArgumentException $e) { throw new BadRequestHttpException($e->getMessage(), $e); }
        $counter = $request->attributes->get('_route') === 'counter_plan';
        $map = $view->build($rep);
        $selectable = $rep->getStatut() === StatutRepresentation::Programme && $rep->getDebut() > $clock->now();
        $selected = $selectable ? SeatMapView::availableSelection($requested, $map) : [];
        $defaultTariff = $em->getRepository(Tarif::class)->findOneBy(['representation' => $rep, 'actif' => true, 'categorie' => CategorieTarif::Plein], ['id' => 'ASC']);
        return $this->render('store/plan.html.twig', [
            'representation' => $rep, 'seatMap' => $map, 'selected' => $selected, 'selectable' => $selectable,
            'initialMode' => 'select', 'counter' => $counter, 'defaultTariff' => $defaultTariff,
            'rejectedCount' => count($requested) - count($selected),
            'representations' => $em->getRepository(Representation::class)->findBy(['evenement' => $rep->getEvenement()], ['debut' => 'ASC']),
        ]);
    }
}
