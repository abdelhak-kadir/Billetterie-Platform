<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Entity\Evenement;
use App\Enum\TypeEvenement;
use App\Form\Model\EvenementFormData;
use App\Form\Type\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\InventairePlaceRepository;
use App\Repository\RepresentationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/evenements', name: 'admin_evenement_')]
final class EvenementController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenements): Response
    {
        $queryBuilder = $evenements->createQueryBuilder('e');

        $query = trim($request->query->getString('q', ''));
        if ($query !== '') {
            $queryBuilder->andWhere('e.titre LIKE :q')->setParameter('q', '%'.$query.'%');
        }

        $type = TypeEvenement::tryFrom($request->query->getString('type', ''));
        if ($type !== null) {
            $queryBuilder->andWhere('e.type = :type')->setParameter('type', $type);
        }

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pagination = new Pagination($request->query->getInt('page', 1), 20, $total);

        $items = $queryBuilder
            ->orderBy('e.titre', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $items,
            'pagination' => $pagination,
            'q' => $query,
            'type' => $type,
            'types' => TypeEvenement::cases(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request, EvenementRepository $evenements, RepresentationRepository $representations, InventairePlaceRepository $inventaires): Response
    {
        $evenement = $evenements->find($id) ?? throw $this->createNotFoundException('Événement introuvable.');

        $reps = $representations->createQueryBuilder('r')
            ->join('r.salle', 's')
            ->addSelect('s')
            ->andWhere('r.evenement = :evenement')
            ->setParameter('evenement', $evenement)
            ->orderBy('r.debut', 'ASC')
            ->getQuery()
            ->getResult();

        $dates = [];
        foreach ($reps as $representation) {
            $key = $representation->getDebut()->setTimezone(new \DateTimeZone('Africa/Casablanca'))->format('Y-m-d');
            $dates[$key] = $representation->getDebut();
        }
        $selectedDate = $request->query->getString('date');
        if (!isset($dates[$selectedDate])) {
            $selectedDate = array_key_first($dates);
        }
        $selectedRepresentations = array_values(array_filter($reps, static fn ($r): bool =>
            $r->getDebut()->setTimezone(new \DateTimeZone('Africa/Casablanca'))->format('Y-m-d') === $selectedDate,
        ));

        return $this->render('admin/evenement/show.html.twig', [
            'evenement' => $evenement,
            'representations' => $selectedRepresentations,
            'dates' => $dates,
            'selectedDate' => $selectedDate,
            'inventoryCounts' => $inventaires->countsByRepresentationIds(array_map(
                static fn ($r): int => (int) $r->getId(), $reps,
            )),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = new EvenementFormData();
        $form = $this->createForm(EvenementType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $evenement = new Evenement($data->titre ?? '', $data->type ?? throw new \LogicException('Type manquant.'), $data->description);
                $entityManager->persist($evenement);
                $entityManager->flush();

                $this->addFlash('success', 'L’événement a été créé.');

                return $this->redirectToRoute('admin_evenement_show', ['id' => $evenement->getId()]);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/evenement/form.html.twig', [
            'form' => $form->createView(),
            'evenement' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EvenementRepository $evenements, EntityManagerInterface $entityManager): Response
    {
        $evenement = $evenements->find($id) ?? throw $this->createNotFoundException('Événement introuvable.');
        $data = EvenementFormData::fromEntity($evenement);
        $form = $this->createForm(EvenementType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $evenement->modifier($data->titre ?? '', $data->type ?? throw new \LogicException('Type manquant.'), $data->description);
                $entityManager->flush();

                $this->addFlash('success', 'L’événement a été modifié.');

                return $this->redirectToRoute('admin_evenement_show', ['id' => $evenement->getId()]);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/evenement/form.html.twig', [
            'form' => $form->createView(),
            'evenement' => $evenement,
        ]);
    }
}
