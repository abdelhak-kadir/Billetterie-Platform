<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Pagination;
use App\Entity\Tarif;
use App\Enum\CategorieTarif;
use App\Form\Model\TarifFormData;
use App\Form\Type\TarifType;
use App\Repository\BilletRepository;
use App\Repository\RepresentationRepository;
use App\Repository\TarifRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tarifs', name: 'admin_tarif_')]
final class TarifController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        TarifRepository $tarifs,
        RepresentationRepository $representations,
    ): Response {
        $queryBuilder = $tarifs->createQueryBuilder('t')
            ->join('t.representation', 'r')->addSelect('r')
            ->join('r.evenement', 'e')->addSelect('e');

        $representationId = $request->query->getInt('representation', 0);
        if ($representationId > 0) {
            $queryBuilder->andWhere('r.id = :representation')->setParameter('representation', $representationId);
        }

        $query = trim($request->query->getString('q', ''));
        if ($query !== '') {
            $queryBuilder->andWhere('t.libelle LIKE :q')->setParameter('q', '%'.$query.'%');
        }

        $actif = $request->query->getString('actif', '');
        if ($actif === '1' || $actif === '0') {
            $queryBuilder->andWhere('t.actif = :actif')->setParameter('actif', $actif === '1');
        }

        $total = (int) (clone $queryBuilder)
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pagination = new Pagination($request->query->getInt('page', 1), 20, $total);

        $items = $queryBuilder
            ->orderBy('r.debut', 'ASC')
            ->addOrderBy('t.libelle', 'ASC')
            ->setMaxResults($pagination->limit)
            ->setFirstResult($pagination->offset())
            ->getQuery()
            ->getResult();

        $representationChoices = $representations->createQueryBuilder('r')
            ->join('r.evenement', 'e')->addSelect('e')
            ->orderBy('r.debut', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/tarif/index.html.twig', [
            'tarifs' => $items,
            'pagination' => $pagination,
            'representations' => $representationChoices,
            'representationId' => $representationId,
            'q' => $query,
            'actif' => $actif,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TarifRepository $tarifs, BilletRepository $billets): Response
    {
        $tarif = $tarifs->find($id) ?? throw $this->createNotFoundException('Tarif introuvable.');

        return $this->render('admin/tarif/show.html.twig', [
            'tarif' => $tarif,
            'usedCount' => $billets->countByTarif($tarif),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = new TarifFormData();
        $form = $this->createForm(TarifType::class, $data);
        $form->handleRequest($request);

        $invalid = false;

        if ($form->isSubmitted()) {
            if ($data->categorie === CategorieTarif::Invitation && $data->prixCentimes !== null && $data->prixCentimes !== 0) {
                $form->get('prixCentimes')->addError(new FormError('Une invitation doit avoir un prix de 0 MAD.'));
                $invalid = true;
            }

            if (! $invalid && $form->isValid()) {
                try {
                    $tarif = new Tarif(
                        $data->representation ?? throw new \LogicException('Représentation manquante.'),
                        $data->libelle ?? '',
                        $data->categorie ?? throw new \LogicException('Catégorie manquante.'),
                        $data->prixCentimes ?? throw new \LogicException('Prix manquant.'),
                    );
                    $entityManager->persist($tarif);
                    $entityManager->flush();

                    $this->addFlash('success', 'Le tarif a été créé.');

                    return $this->redirectToRoute('admin_tarif_show', ['id' => $tarif->getId()]);
                } catch (\InvalidArgumentException $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/tarif/form.html.twig', [
            'form' => $form->createView(),
            'tarif' => null,
            'usedCount' => 0,
            'representationLocked' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        TarifRepository $tarifs,
        BilletRepository $billets,
        EntityManagerInterface $entityManager,
    ): Response {
        $tarif = $tarifs->find($id) ?? throw $this->createNotFoundException('Tarif introuvable.');
        $usedCount = $billets->countByTarif($tarif);
        $representationLocked = $usedCount > 0;
        $data = TarifFormData::fromEntity($tarif);

        $form = $this->createForm(TarifType::class, $data, ['representation_locked' => $representationLocked]);
        $form->handleRequest($request);

        $refused = false;

        if ($form->isSubmitted()) {
            $submitted = $request->request->all($form->getName());
            $submittedRepresentationId = isset($submitted['representation']) && $submitted['representation'] !== ''
                ? (int) $submitted['representation']
                : null;

            if (
                $representationLocked
                && $submittedRepresentationId !== null
                && $submittedRepresentationId !== $tarif->getRepresentation()->getId()
            ) {
                $form->addError(new FormError('Un tarif déjà utilisé ne peut pas être réaffecté à une autre représentation.'));
                $refused = true;
            }

            if ($data->categorie === CategorieTarif::Invitation && $data->prixCentimes !== null && $data->prixCentimes !== 0) {
                $form->get('prixCentimes')->addError(new FormError('Une invitation doit avoir un prix de 0 MAD.'));
                $refused = true;
            }

            if (! $refused && $form->isValid()) {
                try {
                    $tarif->modifier(
                        $data->representation ?? throw new \LogicException('Représentation manquante.'),
                        $data->libelle ?? '',
                        $data->categorie ?? throw new \LogicException('Catégorie manquante.'),
                        $data->prixCentimes ?? throw new \LogicException('Prix manquant.'),
                    );
                    $entityManager->flush();

                    $this->addFlash('success', 'Le tarif a été modifié. Les billets déjà émis conservent leur prix payé.');

                    return $this->redirectToRoute('admin_tarif_show', ['id' => $tarif->getId()]);
                } catch (\InvalidArgumentException $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/tarif/form.html.twig', [
            'form' => $form->createView(),
            'tarif' => $tarif,
            'usedCount' => $usedCount,
            'representationLocked' => $representationLocked,
        ]);
    }

    #[Route('/{id}/desactiver', name: 'deactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deactivate(int $id, Request $request, TarifRepository $tarifs, EntityManagerInterface $entityManager): Response
    {
        $tarif = $tarifs->find($id) ?? throw $this->createNotFoundException('Tarif introuvable.');

        if (! $this->isCsrfTokenValid('tarif_desactiver_'.$tarif->getId(), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException('Jeton de sécurité invalide.');
        }

        if ($tarif->isActif()) {
            $tarif->desactiver();
            $entityManager->flush();
            $this->addFlash('success', 'Le tarif a été désactivé.');
        } else {
            $this->addFlash('info', 'Ce tarif est déjà inactif.');
        }

        return $this->redirectToRoute('admin_tarif_show', ['id' => $tarif->getId()]);
    }
}
