<?php

declare(strict_types=1);
namespace App\Controller\Store;
use App\Entity\Commande;
use App\Service\{DemoMode, TicketingService};
use App\Exception\SaleConflict;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
final class OrderController extends AbstractController
{
    #[Route('/commandes/{id}', name: 'store_order', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $order = $em->find(Commande::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('ORDER_ACCESS', $order);
        return $this->render('store/order.html.twig', ['order' => $order]);
    }

    #[Route('/commandes/{id}/simulation/{action}', name: 'store_payment', requirements: ['id' => '\d+', 'action' => 'confirm|fail|cancel'], methods: ['POST'])]
    public function payment(int $id, string $action, Request $request, EntityManagerInterface $em, TicketingService $sales, DemoMode $demo): Response
    {
        $order = $em->find(Commande::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('ORDER_ACCESS', $order);
        $demo->assertEnabled();
        if (!$this->isCsrfTokenValid('store-'.$action.'-'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Jeton de sécurité invalide.'); }
        try {
            match ($action) { 'confirm' => $sales->confirm($id), 'fail' => $sales->fail($id), 'cancel' => $sales->cancel($id) };
            $this->addFlash('success', 'Traitement simulé enregistré. Consultez le statut de votre commande.');
        } catch (SaleConflict $e) { $this->addFlash('error', $e->getMessage()); }
        return $this->redirectToRoute('store_order', ['id' => $id]);
    }
}
