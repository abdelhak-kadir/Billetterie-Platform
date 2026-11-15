<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Clock\Clock;
use App\Enum\StatutRepresentation;
use App\Presentation\SeatMapView;
use App\Repository\RepresentationRepository;
use App\Service\DemoMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SeatMapController extends AbstractController
{
    #[Route('/admin/representations/{id}/plan', name: 'admin_representation_plan', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request, RepresentationRepository $representations, SeatMapView $view, Clock $clock, DemoMode $demo): Response
    {
        $representation = $representations->find($id) ?? throw $this->createNotFoundException();
        try { $requested = SeatMapView::parseSelection($request->query->all('seats')); }
        catch (\InvalidArgumentException $error) { throw new BadRequestHttpException($error->getMessage(), $error); }
        $map = $view->build($representation);
        $selectable = $demo->enabled() && $representation->getStatut() === StatutRepresentation::Programme && $representation->getDebut() > $clock->now();
        $selected = $selectable ? SeatMapView::availableSelection($requested, $map) : [];
        return $this->render('admin/inventaire/plan.html.twig', [
            'representation' => $representation, 'seatMap' => $map, 'selected' => $selected,
            'selectable' => $selectable, 'rejectedCount' => count($requested) - count($selected),
            'initialMode' => $selected !== [] || $request->query->getString('mode') === 'select' ? 'select' : 'inspect',
            'representations' => $representations->findBy(['evenement' => $representation->getEvenement()], ['debut' => 'ASC']),
        ]);
    }
}
