<?php

declare(strict_types=1);
namespace App\Controller\Store;
use App\Entity\{Representation, Tarif, User};
use App\Enum\CanalCommande;
use App\Exception\SaleConflict;
use App\Presentation\SeatMapView;
use App\Security\TariffAccess;
use App\Service\{DemoMode, TicketingService};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutController extends AbstractController
{
    #[Route('/achat/{id}', name: 'store_checkout', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[Route('/guichet/achat/{id}', name: 'counter_checkout', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function checkout(int $id, Request $request, EntityManagerInterface $em, SeatMapView $view, TicketingService $sales, DemoMode $demo): Response
    {
        $counter = $request->attributes->get('_route') === 'counter_checkout';
        $planRoute = $counter ? 'counter_plan' : 'store_plan';
        $checkoutRoute = $counter ? 'counter_checkout' : 'store_checkout';
        $rep = $em->find(Representation::class, $id) ?? throw $this->createNotFoundException();
        try { $ids = SeatMapView::parseSelection($request->query->all('seats')); }
        catch (\InvalidArgumentException $e) { throw new BadRequestHttpException($e->getMessage(), $e); }
        $map = $view->build($rep);
        if ($ids === []) { $this->addFlash('error', 'Choisissez au moins une place sur le plan.'); return $this->redirectToRoute($planRoute, ['id' => $id]); }
        if (!$this->getUser() instanceof User) {
            if (!$request->isMethod('GET')) { throw $this->createAccessDeniedException(); }
            $request->getSession()->set('_security.main.target_path', $this->generateUrl($checkoutRoute, ['id' => $id, 'seats' => $ids]));
            return $this->redirectToRoute($request->query->getString('authenticate') === 'register' ? 'app_register' : 'app_login');
        }
        $actor = $this->getUser();
        $available = SeatMapView::availableSelection($ids, $map);
        $foreign = array_diff($ids, array_keys($map['seats']));
        if ($foreign !== [] || ($request->isMethod('GET') && count($available) !== count($ids))) {
            $this->addFlash('error', 'Une place n’est plus disponible. Le plan est actualisé ; revoyez votre sélection.');
            return $this->redirectToRoute($planRoute, ['id' => $id, 'seats' => $ids]);
        }
        $seats = array_map(static fn (int $seatId) => $map['seats'][$seatId], $ids);
        $tariffs = array_values(array_filter($em->getRepository(Tarif::class)->findBy(['representation' => $rep, 'actif' => true], ['id' => 'ASC']), static fn (Tarif $t) => in_array($t->getCategorie(), TariffAccess::categories($actor), true)));
        $builder = $this->createFormBuilder(null, ['csrf_message' => 'Le jeton de sécurité est invalide.']);
        if ($counter) { $builder->add('email', EmailType::class, ['label' => 'E-mail du destinataire', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]]); }
        foreach ($seats as $seat) {
            $builder->add('tariff_'.$seat['id'], EntityType::class, [
                'class' => Tarif::class, 'choices' => $tariffs, 'label' => $seat['label'], 'placeholder' => false,
                'choice_label' => static fn (Tarif $t) => $t->getLibelle().' · '.number_format($t->getPrixCentimes()/100, 2, ',', ' ').' MAD',
                'choice_attr' => static fn (Tarif $t) => ['data-price' => $t->getPrixCentimes()],
                'constraints' => [new Assert\NotNull()], 'attr' => ['data-line-tariff' => ''],
            ]);
        }
        $form = $builder->getForm();
        if ($tariffs !== []) { foreach ($seats as $seat) { $form->get('tariff_'.$seat['id'])->setData($tariffs[0]); } }
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $demo->assertEnabled();
            $choices = [];
            foreach ($seats as $seat) { $choices[] = ['inventaire' => $seat['id'], 'tarif' => $form->get('tariff_'.$seat['id'])->getData()->getId()]; }
            try {
                $order = $sales->reserve($id, $choices, $counter ? $form->get('email')->getData() : $actor->getEmail(), $counter ? CanalCommande::Guichet : CanalCommande::EnLigne, $actor);
                $this->addFlash('success', 'Réservation confirmée par le serveur. Finalisez le paiement simulé avant l’échéance.');
                return $this->redirectToRoute('store_order', ['id' => $order->getId()]);
            } catch (SaleConflict $e) {
                $this->addFlash('error', $e->getMessage().' Le plan est actualisé.');
                return $this->redirectToRoute($planRoute, ['id' => $id, 'seats' => $ids]);
            }
        }
        $total = 0;
        foreach ($seats as $seat) { $total += $form->get('tariff_'.$seat['id'])->getData()?->getPrixCentimes() ?? 0; }
        return $this->render('store/checkout.html.twig', ['form' => $form->createView(), 'representation' => $rep, 'seats' => $seats, 'ids' => $ids, 'counter' => $counter, 'total' => $total, 'hasTariffs' => $tariffs !== []]);
    }
}
