<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\{Billet, Commande};
use App\Enum\StatutCommande;

final class SaleTest extends AdminTestCase
{
    public function testReservationPaymentAndRefundThroughCsrfProtectedLocalActions(): void
    {
        $hall = $this->createSalle('Salle ventes', ['Parterre' => ['A' => 2]]);
        $event = $this->createEvenement();
        $rep = $this->createRepresentation($event, $hall, '2099-12-01T19:00:00Z');
        $tariff = $this->createTarif($rep);
        $inventory = $rep->getInventairePlaces()->first();
        $crawler = $this->client->request('GET', '/admin/representations/'.$rep->getId().'/reservation');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Réserver les places')->form();
        $form['form[email]'] = 'demo@example.com';
        $form['form[canal]'] = 'guichet';
        $form['form[inventaires]'] = [(string) $inventory->getId()];
        $form['form[tarif]'] = (string) $tariff->getId();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('Échéance exclusive', $crawler->text());
        $form = $crawler->selectButton('Simuler un paiement réussi')->form();
        $this->client->submit($form); self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('Payée', $crawler->text());
        $this->client->submit($crawler->selectButton('Annuler et rembourser (simulation)')->form());
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('100,00 MAD', $crawler->text());
        $this->entityManager->clear();
        $ticket = $this->entityManager->getRepository(Billet::class)->findOneBy([]);
        self::assertSame(10000, $ticket->getMontantRembourseCentimes());
        self::assertSame(StatutCommande::Annulee, $ticket->getCommande()->getStatut());
    }

    public function testReservedTariffCannotBeMovedEvenWhenTheFormPredatesTheReservation(): void
    {
        $hall = $this->createSalle('Salle tarif réservé', ['Parterre' => ['A' => 1]]);
        $event = $this->createEvenement();
        $r1 = $this->createRepresentation($event, $hall, '2099-12-01T19:00:00Z');
        $r2 = $this->createRepresentation($event, $hall, '2099-12-02T19:00:00Z');
        $tariff = $this->createTarif($r1);
        // Formulaire obtenu avant la réservation, quand le tarif était encore déplaçable.
        $crawler = $this->client->request('GET', '/admin/tarifs/'.$tariff->getId().'/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['tarif_form[representation]'] = (string) $r2->getId();
        $this->entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $service = new \App\Service\TicketingService($this->entityManager, new \App\Tests\Support\FrozenClock(new \DateTimeImmutable('2030-01-01T12:00:00Z')));
        $service->reserve($r1->getId(), [['inventaire' => $r1->getInventairePlaces()->first()->getId(), 'tarif' => $tariff->getId()]], 'test@example.com', \App\Enum\CanalCommande::Guichet);
        $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('déjà utilisé', (string) $this->client->getResponse()->getContent());
        $this->entityManager->clear();
        self::assertSame($r1->getId(), $this->entityManager->find(\App\Entity\Tarif::class, $tariff->getId())->getRepresentation()->getId());
    }

    public function testForgedSimulationAndRepresentationCancellationAreRefused(): void
    {
        $hall = $this->createSalle('Salle CSRF S2', ['Parterre' => ['A' => 1]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall);
        $order = $this->createCommande();
        foreach (['confirm', 'fail', 'cancel'] as $action) {
            $this->client->request('POST', '/admin/commandes/'.$order->getId().'/simulation/'.$action, ['_token' => 'invalid']);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/admin/representations/'.$rep->getId().'/annulation', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->entityManager->clear();
        self::assertSame(StatutCommande::EnAttente, $this->entityManager->find(Commande::class, $order->getId())->getStatut());
    }
}
