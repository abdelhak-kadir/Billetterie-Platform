<?php

declare(strict_types=1);
namespace App\Controller\Store;
use App\Repository\RepresentationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class CounterController extends AbstractController
{
    #[Route('/guichet', name: 'counter_index', methods: ['GET'])]
    public function index(RepresentationRepository $representations): Response
    {
        return $this->render('store/counter.html.twig', ['representations' => $representations->findBy([], ['debut' => 'ASC'])]);
    }
}
