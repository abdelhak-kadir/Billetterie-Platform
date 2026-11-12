<?php

declare(strict_types=1);
namespace App\Controller\Store;
use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
}
