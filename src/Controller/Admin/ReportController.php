<?php

declare(strict_types=1);
namespace App\Controller\Admin;
use App\Repository\{RepresentationRepository, ZoneRepository};
use App\Service\SalesReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
final class ReportController extends AbstractController
{
    #[Route('/admin/indicateurs', name: 'admin_report', methods: ['GET'])]
    public function index(Request $request, SalesReport $report, RepresentationRepository $reps, ZoneRepository $zones): Response
    {
        $representation = max(0, $request->query->getInt('representation')); $zone = max(0, $request->query->getInt('zone'));
        $channel = $request->query->getString('canal');
        if (!in_array($channel, ['en_ligne','guichet'], true)) { $channel = ''; }
        return $this->render('admin/report.html.twig', [
            'report'=>$report->build($representation, $zone, $channel), 'representations'=>$reps->findBy([], ['debut'=>'ASC']),
            'zones'=>$zones->findBy([], ['id'=>'ASC']), 'representationId'=>$representation, 'zoneId'=>$zone, 'channel'=>$channel,
        ]);
    }
}
