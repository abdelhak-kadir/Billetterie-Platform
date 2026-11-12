<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{InventairePlace, Representation, Tarif};
use App\Enum\CanalCommande;
use App\Exception\SaleConflict;
use App\Presentation\SeatMapView;
use App\Repository\{CommandeRepository, InventairePlaceRepository, RepresentationRepository};
use App\Service\TicketingService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\{EmailType, EnumType};
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/admin', name: 'admin_sale_')]
final class SaleController extends AbstractController
{
    #[Route('/representations/{id}/reservation', name: 'reserve', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reserve(int $id, Request $request, RepresentationRepository $representations, TicketingService $sales, SeatMapView $seatMapView, InventairePlaceRepository $inventories): Response
    {
        $representation = $representations->find($id) ?? throw $this->createNotFoundException();
        $form = $this->createFormBuilder(null, ['csrf_message' => 'Le jeton de sécurité est invalide.'])
            ->add('email', EmailType::class, ['label' => 'E-mail du client fictif', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]])
            ->add('canal', EnumType::class, ['class' => CanalCommande::class, 'label' => 'Canal', 'choice_label' => static fn (CanalCommande $c) => $c->label(), 'constraints' => [new Assert\NotNull()]])
            ->add('inventaires', EntityType::class, [
                'class' => InventairePlace::class, 'multiple' => true, 'label' => 'Places (sélection multiple)',
                'choice_label' => static fn (InventairePlace $i) => $i->getPlace()->getLibelle().' — '.$i->getStatut()->label(),
                'query_builder' => static fn ($r) => $r->createQueryBuilder('i')->join('i.place', 'p')->addSelect('p')->join('p.zone', 'z')->addSelect('z')
                    ->andWhere('i.representation = :r')->setParameter('r', $representation)->orderBy('z.nom', 'ASC')->addOrderBy('p.rangee', 'ASC')->addOrderBy('p.numero', 'ASC'),
                'constraints' => [new Assert\Count(min: 1, max: 400)], 'attr' => ['size' => 10],
                'help' => 'Maintenez Ctrl ou Cmd pour sélectionner plusieurs places. La disponibilité est revérifiée sous verrou ; toutes les places doivent être obtenues ensemble.',
            ])
            ->add('tarif', EntityType::class, [
                'class' => Tarif::class, 'label' => 'Tarif pour les places sélectionnées', 'placeholder' => 'Choisir un tarif',
                'choice_label' => static fn (Tarif $t) => $t->getLibelle().' — '.number_format($t->getPrixCentimes() / 100, 2, ',', ' ').' MAD',
                'query_builder' => static fn ($r) => $r->createQueryBuilder('t')->andWhere('t.representation = :r AND t.actif = true')->setParameter('r', $representation)->orderBy('t.id', 'ASC'),
                'constraints' => [new Assert\NotNull()],
            ])->getForm();
        $fromPlan = $request->query->getBoolean('from_plan');
        if ($request->isMethod('GET')) {
            try { $requested = SeatMapView::parseSelection($request->query->all('seats')); }
            catch (\InvalidArgumentException $error) { throw new BadRequestHttpException($error->getMessage(), $error); }
            if ($requested !== []) {
                $map = $seatMapView->build($representation);
                $available = SeatMapView::availableSelection($requested, $map);
                if ($fromPlan && count($available) !== count($requested)) {
                    $this->addFlash('error', 'La disponibilité a changé. Aucun siège n’a été réservé ; le plan est actualisé.');
                    return $this->redirectToRoute('admin_representation_plan', ['id' => $id, 'mode' => 'select', 'seats' => $requested]);
                }
                $form->get('inventaires')->setData($available === [] ? [] : $inventories->findBy(['id' => $available, 'representation' => $representation]));
            }
        }
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $choices = [];
                foreach ($data['inventaires'] as $inventory) { $choices[] = ['inventaire' => $inventory->getId(), 'tarif' => $data['tarif']->getId()]; }
                $order = $sales->reserve($id, $choices, $data['email'], $data['canal']);
                $this->addFlash('success', 'Places réservées. Le paiement reste simulé, à confirmer avant l’échéance.');
                return $this->redirectToRoute('admin_commande_show', ['id' => $order->getId()]);
            } catch (SaleConflict|\InvalidArgumentException $error) {
                if ($fromPlan && $error instanceof SaleConflict) {
                    $this->addFlash('error', $error->getMessage().' Le plan a été actualisé, revoyez votre sélection.');
                    return $this->redirectToRoute('admin_representation_plan', ['id' => $id, 'mode' => 'select', 'seats' => array_column($choices, 'inventaire')]);
                }
                $form->addError(new FormError($error->getMessage()));
            }
        }
        return $this->render('admin/sale/reserve.html.twig', ['form' => $form->createView(), 'representation' => $representation]);
    }

    #[Route('/commandes/{id}/simulation/{action}', name: 'transition', requirements: ['id' => '\d+', 'action' => 'confirm|fail|cancel'], methods: ['POST'])]
    public function transition(int $id, string $action, Request $request, CommandeRepository $orders, TicketingService $sales): Response
    {
        $orders->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('sale-'.$action.'-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        try {
            match ($action) {
                'confirm' => $sales->confirm($id),
                'fail' => $sales->fail($id),
                'cancel' => $sales->cancel($id),
            };
            $this->addFlash('success', 'Traitement simulé enregistré. Le résultat existant est conservé en cas de rejeu.');
        } catch (SaleConflict $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('admin_commande_show', ['id' => $id]);
    }

    #[Route('/representations/{id}/annulation', name: 'cancel_representation', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelRepresentation(int $id, Request $request, RepresentationRepository $representations, TicketingService $sales): Response
    {
        $representations->find($id) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('cancel-representation-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        try {
            $sales->cancelRepresentation($id);
            $this->addFlash('success', 'Représentation annulée, places bloquées et remboursements simulés enregistrés.');
        } catch (SaleConflict|\LogicException $error) { $this->addFlash('error', $error->getMessage()); }
        return $this->redirectToRoute('admin_representation_show', ['id' => $id]);
    }
}
