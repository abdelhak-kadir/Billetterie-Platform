<?php

declare(strict_types=1);
namespace App\Controller\Store;
use App\Entity\Billet;
use App\Enum\StatutBillet;
use App\Service\{TicketCode, TicketPdf};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{HeaderUtils, Request, Response};
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
final class TicketController extends AbstractController
{
    #[Route('/billets/{id}', name: 'store_ticket', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $ticket = $em->find(Billet::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('ORDER_ACCESS', $ticket);
        return $this->render('store/ticket.html.twig', ['ticket' => $ticket]);
    }

    #[Route('/billets/{id}/pdf', name: 'store_ticket_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pdf(int $id, EntityManagerInterface $em, TicketPdf $pdf): Response
    {
        $ticket = $em->find(Billet::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('ORDER_ACCESS', $ticket);
        if ($ticket->getStatut() !== StatutBillet::Valide || $ticket->getCommande()->getPayeA() === null) { throw new GoneHttpException('Ce billet est annulé ou non confirmé. Le téléchargement est indisponible ; son historique reste consultable.'); }
        $filename = 'horizon-'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $ticket->getReference()).'.pdf';
        return new Response($pdf->render($ticket), 200, ['Content-Type'=>'application/pdf', 'Content-Disposition'=>HeaderUtils::makeDisposition('attachment', $filename), 'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
    }

    #[Route('/guichet/controle', name: 'counter_check_ticket', methods: ['GET'])]
    public function check(Request $request, EntityManagerInterface $em, TicketCode $codes): Response
    {
        $ref = $codes->reference($request->query->getString('code'));
        $ticket = $ref === null ? null : $em->getRepository(Billet::class)->findOneBy(['reference' => $ref]);
        if (!$ticket || $ticket->getCommande()->getPayeA() === null) { throw $this->createNotFoundException('Code de billet invalide.'); }
        return $this->render('store/ticket-check.html.twig', ['ticket'=>$ticket]);
    }
}
