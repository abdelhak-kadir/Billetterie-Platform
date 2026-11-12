<?php

declare(strict_types=1);
namespace App\Controller\Store;
use App\Entity\Billet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
}
