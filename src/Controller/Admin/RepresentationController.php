<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Entity\Representation;
use App\Form\Model\RepresentationFormData;
use App\Form\Type\RepresentationType;
use App\Repository\EvenementRepository;
use App\Repository\InventairePlaceRepository;
use App\Repository\RepresentationRepository;
use App\Repository\SalleRepository;
use App\Service\RepresentationInventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/representations', name: 'admin_representation_')]
final class RepresentationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        RepresentationRepository $representations,
        InventairePlaceRepository $inventaires,
        EvenementRepository $evenements,
        SalleRepository $salles,
    ): Response {
        $queryBuilder = $representations->createQueryBuilder('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->join('r.salle', 's')->addSelect('s');

        $evenementId = $request->query->getInt('evenement', 0);
        if ($evenementId > 0) {
            $queryBuilder->andWhere('e.id = :evenement')->setParameter('evenement', $evenementId);
        }

        $salleId = $request->query->getInt('salle', 0);
        if ($salleId > 0) {
            $queryBuilder->andWhere('s.id = :salle')->setParameter('salle', $salleId);
        }

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pagination = new Pagination($request->query->getInt('page', 1), 20, $total);

        $items = $queryBuilder
            ->orderBy('r.debut', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        $counts = $inventaires->countsByRepresentationIds(array_map(
            static fn (Representation $representation): int => (int) $representation->getId(),
            $items,
        ));

        return $this->render('admin/representation/index.html.twig', [
            'representations' => $items,
            'counts' => $counts,
            'pagination' => $pagination,
            'evenements' => $evenements->findBy([], ['titre' => 'ASC']),
            'salles' => $salles->findBy([], ['nom' => 'ASC']),
            'evenementId' => $evenementId,
            'salleId' => $salleId,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, RepresentationRepository $representations, InventairePlaceRepository $inventaires): Response
    {
        $representation = $representations->find($id) ?? throw $this->createNotFoundException('Représentation introuvable.');
        $counts = $inventaires->countsByRepresentationIds([(int) $representation->getId()])[(int) $representation->getId()] ?? [];

        return $this->render('admin/representation/show.html.twig', [
            'representation' => $representation,
            'counts' => $counts,
            'tarifs' => $representation->getTarifs(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        RepresentationInventoryService $inventoryService,
        InventairePlaceRepository $inventaires,
    ): Response {
        $data = new RepresentationFormData();
        $form = $this->createForm(RepresentationType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $representation = $inventoryService->createRepresentation(
                    $data->evenement ?? throw new \LogicException('Événement manquant.'),
                    $data->salle ?? throw new \LogicException('Salle manquante.'),
                    $data->debut ?? throw new \LogicException('Date manquante.'),
                );

                $count = $inventaires->countForRepresentation($representation);
                $this->addFlash('success', sprintf('La représentation a été créée avec %d places inventoriées.', $count));

                return $this->redirectToRoute('admin_representation_show', ['id' => $representation->getId()]);
            } catch (\Throwable $exception) {
                $form->addError(new FormError('La représentation n’a pas pu être créée : '.$exception->getMessage()));
            }
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form->createView(),
            'representation' => null,
            'salleLocked' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        RepresentationRepository $representations,
        InventairePlaceRepository $inventaires,
        EntityManagerInterface $entityManager,
    ): Response {
        $representation = $representations->find($id) ?? throw $this->createNotFoundException('Représentation introuvable.');
        $inventoryCount = $inventaires->countForRepresentation($representation);
        $salleLocked = $inventoryCount > 0;
        $data = RepresentationFormData::fromEntity($representation);

        $form = $this->createForm(RepresentationType::class, $data, ['salle_locked' => $salleLocked]);
        $form->handleRequest($request);

        $refused = false;

        if ($form->isSubmitted()) {
            $submitted = $request->request->all($form->getName());
            $submittedSalleId = isset($submitted['salle']) && $submitted['salle'] !== ''
                ? (int) $submitted['salle']
                : null;

            if ($salleLocked && $submittedSalleId !== null && $submittedSalleId !== $representation->getSalle()->getId()) {
                $form->addError(new FormError('La salle ne peut pas être changée après la création de l’inventaire.'));
                $refused = true;
            }

            if (! $refused && $form->isValid()) {
                $representation->modifier(
                    $data->evenement ?? throw new \LogicException('Événement manquant.'),
                    $data->salle ?? throw new \LogicException('Salle manquante.'),
                    $data->debut ?? throw new \LogicException('Date manquante.'),
                );
                $entityManager->flush();

                $this->addFlash('success', 'La représentation a été modifiée.');

                return $this->redirectToRoute('admin_representation_show', ['id' => $representation->getId()]);
            }
        }

        return $this->render('admin/representation/form.html.twig', [
            'form' => $form->createView(),
            'representation' => $representation,
            'salleLocked' => $salleLocked,
            'inventoryCount' => $inventoryCount,
        ]);
    }
}
