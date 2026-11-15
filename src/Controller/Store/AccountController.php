<?php

declare(strict_types=1);
namespace App\Controller\Store;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/compte', name: 'account', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('store/account.html.twig', ['orders' => $em->getRepository(Commande::class)->findBy(['owner' => $this->getUser()], ['id' => 'DESC'])]);
    }
}
