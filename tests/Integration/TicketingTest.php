<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Billet, Commande, InventairePlace, ReservationItem, Tarif};
use App\Enum\{CanalCommande, CategorieTarif, StatutBillet, StatutCommande, StatutInventairePlace, StatutRemboursement};
use App\Exception\SaleConflict;
use App\Service\{RepresentationInventoryService, TicketingService};
use App\Tests\Support\FrozenClock;

final class TicketingTest extends TicketingTestCase
{
    public function testAtomicReservationAndIndependentR2(): void
    {
        $order = $this->reserve([0, 1]);
        self::assertSame('2030-01-01T12:10:00+00:00', $order->getReservationExpireA()->format('c'));
        try { $this->reserve([1, 2]); self::fail('Conflit attendu'); } catch (SaleConflict $e) { self::assertStringContainsString('disponible', $e->getMessage()); }
        $this->entityManager->clear();
        self::assertSame(1, $this->entityManager->getRepository(Commande::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(ReservationItem::class)->count([]));
        self::assertSame(StatutInventairePlace::Disponible, $this->entityManager->find(InventairePlace::class, $this->inventories[2])->getStatut());
        self::assertSame(3, $this->entityManager->getRepository(InventairePlace::class)->count(['representation' => $this->r2, 'statut' => StatutInventairePlace::Disponible]));
    }

    public function testConfirmationReplayAndLateFailureKeepOneSale(): void
    {
        $id = $this->reserve([0, 1])->getId();
        $order = $this->sales->confirm($id);
        $paid = $order->getPayeA();
        $this->sales->confirm($id); $this->sales->fail($id);
        $this->entityManager->clear();
        self::assertSame(2, $this->entityManager->getRepository(Billet::class)->count([]));
        self::assertSame(StatutCommande::Payee, $this->entityManager->find(Commande::class, $id)->getStatut());
        self::assertEquals($paid, $this->entityManager->find(Commande::class, $id)->getPayeA());
        foreach ($this->entityManager->getRepository(Billet::class)->findAll() as $ticket) {
            self::assertSame(StatutInventairePlace::Vendue, $ticket->getInventairePlace()->getStatut());
        }
    }

    public function testFailureAndCancellationReleaseOnlyOwnedReservations(): void
    {
        $id = $this->reserve([0, 1])->getId();
        $this->sales->fail($id); $this->sales->fail($id);
        $newId = $this->reserve([0])->getId();
        $this->sales->fail($id); $this->sales->cancel($id);
        self::assertSame($newId, $this->entityManager->find(InventairePlace::class, $this->inventories[0])->getCommandeReservation()->getId());
        $this->sales->cancel($newId); $this->sales->cancel($newId);
        self::assertSame(StatutCommande::Annulee, $this->entityManager->find(Commande::class, $newId)->getStatut());
        self::assertSame(0, $this->entityManager->getRepository(Billet::class)->count([]));
    }

    public function testExactDeadlineExpiresAndCronIsReplayable(): void
    {
        $id = $this->reserve([0, 1])->getId();
        $this->clock->advance('+600 seconds');
        try { $this->sales->confirm($id); self::fail('Expiration attendue'); } catch (SaleConflict $e) { self::assertStringContainsString('expiré', $e->getMessage()); }
        $this->entityManager->clear();
        self::assertSame(StatutCommande::Expiree, $this->entityManager->find(Commande::class, $id)->getStatut());
        self::assertSame(0, $this->sales->expireDue());
        self::assertSame(0, $this->entityManager->getRepository(Billet::class)->count([]));
        self::assertSame(3, $this->entityManager->getRepository(InventairePlace::class)->count(['representation' => $this->r1, 'statut' => StatutInventairePlace::Disponible]));
    }

    public function testExpiredPlaceCanBeReclaimedBeforeCronWithoutReleasingNewOwner(): void
    {
        $oldId = $this->reserve([0, 1])->getId();
        $this->clock->advance('+600 seconds');
        $newId = $this->reserve([0])->getId();
        self::assertFalse($this->sales->expire($oldId));
        self::assertSame($newId, $this->entityManager->find(InventairePlace::class, $this->inventories[0])->getCommandeReservation()->getId());
        self::assertSame(StatutInventairePlace::Disponible, $this->entityManager->find(InventairePlace::class, $this->inventories[1])->getStatut());
    }

    public function testPriceIsFixedAtReservationAndRefundUsesPaidPrice(): void
    {
        $id = $this->reserve()->getId();
        $this->entityManager->find(Tarif::class, $this->tariff)->changerPrix(12000);
        $this->entityManager->flush();
        $this->sales->confirm($id);
        $this->sales->cancel($id); $this->sales->cancel($id); $this->sales->confirm($id);
        $this->entityManager->clear();
        $tickets = $this->entityManager->getRepository(Billet::class)->findAll();
        self::assertCount(1, $tickets);
        self::assertSame(10000, $tickets[0]->getPrixPayeCentimes());
        self::assertSame(10000, $tickets[0]->getMontantRembourseCentimes());
        self::assertSame(StatutRemboursement::Rembourse, $tickets[0]->getStatutRemboursement());
        self::assertSame(StatutCommande::Annulee, $tickets[0]->getCommande()->getStatut());
        $newId = $this->reserve()->getId(); $this->sales->confirm($newId);
        self::assertSame(2, $this->entityManager->getRepository(Billet::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(Billet::class)->count(['statut' => StatutBillet::Valide]));
    }

    public function testFreeInvitationAndRepresentationCancellationPreserveHistory(): void
    {
        $paid = $this->reserve([0], $this->invitation)->getId(); $this->sales->confirm($paid);
        $pending = $this->reserve([1])->getId();
        $this->sales->cancelRepresentation($this->r1); $this->sales->cancelRepresentation($this->r1);
        $this->entityManager->clear();
        self::assertSame(StatutCommande::Annulee, $this->entityManager->find(Commande::class, $pending)->getStatut());
        $ticket = $this->entityManager->getRepository(Billet::class)->findOneBy([]);
        self::assertSame(0, $ticket->getPrixPayeCentimes()); self::assertSame(0, $ticket->getMontantRembourseCentimes());
        self::assertSame(StatutRemboursement::SansObjet, $ticket->getStatutRemboursement());
        self::assertSame(3, $this->entityManager->getRepository(InventairePlace::class)->count(['representation' => $this->r1, 'statut' => StatutInventairePlace::Bloquee]));
        self::assertSame(3, $this->entityManager->getRepository(InventairePlace::class)->count(['representation' => $this->r2, 'statut' => StatutInventairePlace::Disponible]));
        $this->expectException(SaleConflict::class); $this->reserve([2]);
    }

    public function testConfirmationOneSecondBeforeDeadlineSurvivesLaterExpiry(): void
    {
        $id = $this->reserve()->getId(); $this->clock->advance('+599 seconds');
        $this->sales->confirm($id); $this->clock->advance('+1 second');
        self::assertFalse($this->sales->expire($id));
        self::assertSame(StatutInventairePlace::Vendue, $this->entityManager->find(InventairePlace::class, $this->inventories[0])->getStatut());
    }

    public function testCleanupCommandExpiresWholeOrdersAndCanBeReplayed(): void
    {
        $this->reserve([0, 1]); $this->reserve([2]);
        $this->clock->advance('+600 seconds');
        $tester = new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\ExpireReservationsCommand($this->sales));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('2 commande(s)', $tester->getDisplay());
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('0 commande(s)', $tester->getDisplay());
        self::assertSame(2, $this->entityManager->getRepository(Commande::class)->count(['statut' => StatutCommande::Expiree]));
    }

    public function testHistoricMultiRepresentationOrderIsOnlyPartiallyRefundedWhenOneShowIsCancelled(): void
    {
        $em = $this->entityManager;
        $r2 = $em->find(\App\Entity\Representation::class, $this->r2);
        $tariff2 = new Tarif($r2, 'Plein R2', CategorieTarif::Plein, 8000);
        $em->persist($tariff2);
        $order = new Commande('HISTORY-MULTI', 'legacy@example.com', CanalCommande::Guichet, $this->clock->now());
        $em->persist($order);
        $i1 = $em->find(InventairePlace::class, $this->inventories[0]);
        $i2 = $em->getRepository(InventairePlace::class)->findOneBy(['representation' => $r2]);
        foreach ([[$i1, $em->find(Tarif::class, $this->tariff), 10000], [$i2, $tariff2, 8000]] as $n => [$inventory, $tariff, $price]) {
            $inventory->reserver($order, $this->clock->now()->modify('+10 minutes'));
            $inventory->vendre();
            $em->persist(new Billet('HISTORY-'.$n, $order, $inventory, $tariff, $price, $this->clock->now()));
        }
        $order->marquerPayee($this->clock->now()); $em->flush(); $id = $order->getId();
        $this->sales->cancelRepresentation($this->r1);
        $em->clear();
        self::assertSame(StatutCommande::Payee, $em->find(Commande::class, $id)->getStatut());
        self::assertSame(1, $em->getRepository(Billet::class)->count(['statut' => StatutBillet::Valide]));
        self::assertSame(10000, (int) $em->getConnection()->fetchOne('SELECT SUM(montant_rembourse_centimes) FROM billet'));
        $this->sales->cancel($id); $this->sales->cancel($id);
        self::assertSame(18000, (int) $em->getConnection()->fetchOne('SELECT SUM(montant_rembourse_centimes) FROM billet'));
    }

    public function testInactiveTariffOrStartedShowCannotBeConfirmedAndCancelledSeatStaysBlockedAfterStart(): void
    {
        $id = $this->reserve()->getId();
        $this->entityManager->find(Tarif::class, $this->tariff)->desactiver(); $this->entityManager->flush();
        try { $this->sales->confirm($id); self::fail('Tarif inactif'); } catch (SaleConflict) {}
        self::assertSame(0, $this->entityManager->getRepository(Billet::class)->count([]));
        $this->entityManager->find(Tarif::class, $this->tariff)->activer(); $this->entityManager->flush();
        $this->sales->confirm($id);
        $this->clock->advance('+2 months'); $this->sales->cancel($id);
        self::assertSame(StatutInventairePlace::Bloquee, $this->entityManager->find(InventairePlace::class, $this->inventories[0])->getStatut());
        $this->expectException(SaleConflict::class); $this->reserve([1]);
    }

    public function testIncoherentTariffAndDuplicateSeatsAreRejected(): void
    {
        foreach ([
            [['inventaire' => $this->inventories[0], 'tarif' => $this->tariff], ['inventaire' => $this->inventories[0], 'tarif' => $this->tariff]],
            [['inventaire' => $this->inventories[0], 'tarif' => 999999]],
        ] as $choices) {
            try { $this->sales->reserve($this->r1, $choices, 'client@example.com', CanalCommande::Guichet); self::fail('Conflit attendu'); } catch (SaleConflict) {}
        }
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
    }
}
