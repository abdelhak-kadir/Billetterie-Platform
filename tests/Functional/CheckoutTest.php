<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\{Billet, Commande, InventairePlace};
use App\Enum\{CanalCommande, CategorieTarif, StatutCommande, StatutInventairePlace};
use App\Service\{DemoMode, TicketingService};

final class CheckoutTest extends AdminTestCase
{
    private function scenario(): array
    {
        $hall = $this->createSalle('Horizon parcours', ['Parterre' => ['A' => 4], 'Balcon' => ['A' => 2]]);
        $event = $this->createEvenement('Concert Horizon');
        $r1 = $this->createRepresentation($event, $hall, '2099-12-01T19:00:00Z');
        $r2 = $this->createRepresentation($event, $hall, '2099-12-02T19:00:00Z');
        $tariffs = [];
        foreach (CategorieTarif::cases() as $category) {
            $tariffs[$category->value] = $this->createTarif($r1, $category->label(), $category, match ($category) { CategorieTarif::Plein => 10000, CategorieTarif::Reduit => 8000, CategorieTarif::Groupe => 6000, CategorieTarif::Invitation => 0 });
        }
        $ids = array_map(static fn ($i) => $i->getId(), $r1->getInventairePlaces()->toArray());
        return [$r1, $r2, $tariffs, $ids];
    }
    private function url($rep, array $ids, bool $counter = false): string
    {
        return ($counter ? '/guichet/achat/' : '/achat/').$rep->getId().'?'.http_build_query(['seats' => $ids]);
    }
    public function testClientCompletePurchaseUpdatesEveryChannelButNotR2(): void
    {
        [$r1,$r2,$tariffs,$ids] = $this->scenario();
        $buyer = $this->createUser('buyer@example.com'); $this->client->loginUser($buyer);
        $crawler = $this->client->request('GET', $this->url($r1, array_slice($ids, 0, 2)));
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('[data-line-tariff]'));
        self::assertCount(2, $crawler->filter('[data-line-tariff] option')); // Plein uniquement.
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
        $this->client->submit($crawler->selectButton('Réserver ces places')->form());
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('Échéance serveur', $crawler->text());
        $orderId = (int) basename($this->client->getRequest()->getPathInfo());
        $this->client->submit($crawler->selectButton('Simuler un paiement réussi')->form());
        self::assertResponseRedirects(); $this->client->followRedirect();
        $this->entityManager->clear(); $order = $this->entityManager->find(Commande::class, $orderId);
        self::assertSame(CanalCommande::EnLigne, $order->getCanal());
        self::assertSame($buyer->getId(), $order->getOwner()->getId());
        self::assertSame(StatutCommande::Payee, $order->getStatut());
        self::assertCount(2, $order->getBillets());
        foreach ($order->getBillets() as $ticket) { self::assertSame(10000, $ticket->getPrixPayeCentimes()); }
        $this->client->loginUser($this->createUser('counter@example.com', 'ROLE_GUICHETIER'));
        $crawler = $this->client->request('GET', '/guichet/representations/'.$r1->getId().'/plan');
        self::assertSame('vendue', $crawler->filter('[data-seat-id="'.$ids[0].'"]')->attr('data-status'));
        $this->client->loginUser($this->createUser('manager@example.com', 'ROLE_ADMIN'));
        $crawler = $this->client->request('GET', '/admin/representations/'.$r1->getId().'/plan');
        self::assertSame('vendue', $crawler->filter('[data-seat-id="'.$ids[0].'"]')->attr('data-status'));
        $crawler = $this->client->request('GET', '/representations/'.$r2->getId().'/plan');
        self::assertSame('disponible', $crawler->filter('[data-label="Parterre, rangée A, place 1"]')->attr('data-status'));
    }
    public function testSelectionSurvivesRealLoginAndIsRevalidated(): void
    {
        [$r1,,$tariffs,$ids] = $this->scenario();
        $this->createUser('login@example.com'); $this->client->restart();
        $url = $this->url($r1, [$ids[0],$ids[1]]);
        $this->client->request('GET', $url); self::assertResponseRedirects('/connexion');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'login@example.com', '_password' => 'HorizonDemo2026!']));
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect(); self::assertCount(2, $crawler->filter('[data-line-tariff]'));
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
    }
    public function testCrossChannelConflictHasNoPartialHoldAndKeepsOnlyFreeSeat(): void
    {
        [$r1,,$tariffs,$ids] = $this->scenario();
        $this->client->loginUser($this->createUser('buyer@example.com'));
        $crawler = $this->client->request('GET', $this->url($r1, [$ids[0],$ids[1]]));
        $form = $crawler->selectButton('Réserver ces places')->form();
        $service = static::getContainer()->get(TicketingService::class);
        $service->reserve($r1->getId(), [['inventaire'=>$ids[0], 'tarif'=>$tariffs['plein']->getId()]], 'counter@example.com', CanalCommande::Guichet);
        $this->client->submit($form); self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('plan est actualisé', $crawler->text());
        self::assertSame('false', $crawler->filter('[data-seat-id="'.$ids[0].'"]')->attr('data-initial-selected'));
        self::assertSame('true', $crawler->filter('[data-seat-id="'.$ids[1].'"]')->attr('data-initial-selected'));
        $this->entityManager->clear();
        self::assertSame(1, $this->entityManager->getRepository(Commande::class)->count([]));
        self::assertSame(StatutInventairePlace::Disponible, $this->entityManager->find(InventairePlace::class, $ids[1])->getStatut());
    }
    public function testForgedTariffAndCsrfCannotReserve(): void
    {
        [$r1,,$tariffs,$ids] = $this->scenario();
        $this->client->loginUser($this->createUser('buyer@example.com'));
        $url = $this->url($r1, [$ids[0]]);
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Réserver ces places')->form()->getPhpValues();
        $values['form']['tariff_'.$ids[0]] = $tariffs['invitation']->getId();
        $this->client->request('POST', $url, $values); self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
        $values['form']['tariff_'.$ids[0]] = $tariffs['plein']->getId(); $values['form']['_token'] = 'invalid';
        $this->client->request('POST', $url, $values); self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
    }
    public function testFailedPaymentAndExpiredHoldNeverIssueTickets(): void
    {
        [$r1,,$tariffs,$ids] = $this->scenario();
        $buyer = $this->createUser('buyer@example.com'); $this->client->loginUser($buyer);
        $sales = static::getContainer()->get(TicketingService::class);
        $order = $sales->reserve($r1->getId(), [['inventaire'=>$ids[0], 'tarif'=>$tariffs['plein']->getId()]], $buyer->getEmail(), CanalCommande::EnLigne, $buyer);
        $crawler = $this->client->request('GET', '/commandes/'.$order->getId());
        $this->client->submit($crawler->selectButton('Simuler un paiement échoué')->form()); self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertSame(StatutCommande::Echouee, $this->entityManager->find(Commande::class, $order->getId())->getStatut());
        self::assertSame(StatutInventairePlace::Disponible, $this->entityManager->find(InventairePlace::class, $ids[0])->getStatut());
        $buyer = $this->entityManager->find(\App\Entity\User::class, $buyer->getId());
        $sales = new TicketingService($this->entityManager, new \App\Tests\Support\FrozenClock(new \DateTimeImmutable('2000-01-01T00:00:00Z')), 60);
        $expired = $sales->reserve($r1->getId(), [['inventaire'=>$ids[1], 'tarif'=>$tariffs['plein']->getId()]], $buyer->getEmail(), CanalCommande::EnLigne, $buyer);
        // Créée avec une horloge passée, la réservation est échue pour l'horloge HTTP réelle.
        $crawler = $this->client->request('GET', '/commandes/'.$expired->getId());
        $this->client->submit($crawler->selectButton('Simuler un paiement réussi')->form()); self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertSame(StatutCommande::Expiree, $this->entityManager->find(Commande::class, $expired->getId())->getStatut());
        self::assertSame(0, $this->entityManager->getRepository(Billet::class)->count([]));
    }

    public function testCounterSaleUsesAuthorizedTariffsAndServerChannel(): void
    {
        [$r1,,$tariffs,$ids] = $this->scenario();
        $staff = $this->createUser('counter@example.com', 'ROLE_GUICHETIER'); $this->client->loginUser($staff);
        $crawler = $this->client->request('GET', '/guichet/representations/'.$r1->getId().'/plan');
        self::assertSame('10000', $crawler->filter('[data-selected-total]')->attr('data-unit-price'));
        $url = $this->url($r1, [$ids[0], $ids[1]], true);
        $crawler = $this->client->request('GET', $url);
        self::assertCount(6, $crawler->filter('[data-line-tariff] option'));
        self::assertCount(0, $crawler->filter('option[value="'.$tariffs['invitation']->getId().'"]'));
        $form = $crawler->selectButton('Réserver ces places')->form(['form[email]' => 'recipient@example.com', 'form[tariff_'.$ids[0].']' => (string) $tariffs['reduit']->getId(), 'form[tariff_'.$ids[1].']' => (string) $tariffs['groupe']->getId()]);
        $this->client->submit($form); self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('140,00 MAD', $crawler->text());
        $this->client->submit($crawler->selectButton('Simuler un paiement réussi')->form()); self::assertResponseRedirects();
        $this->entityManager->clear(); $order = $this->entityManager->getRepository(Commande::class)->findOneBy([]);
        self::assertSame(CanalCommande::Guichet, $order->getCanal()); self::assertNull($order->getOwner());
        self::assertSame('recipient@example.com', $order->getEmailClient());
        self::assertSame(14000, array_sum(array_map(static fn ($b) => $b->getPrixPayeCentimes(), $order->getBillets()->toArray())));
    }

    public function testPaymentOwnerCsrfAndDemoModeAreEnforced(): void
    {
        [$r1,,$tariffs,$ids] = $this->scenario();
        $buyer = $this->createUser('buyer@example.com');
        $order = static::getContainer()->get(TicketingService::class)->reserve($r1->getId(), [['inventaire'=>$ids[0], 'tarif'=>$tariffs['plein']->getId()]], $buyer->getEmail(), CanalCommande::EnLigne, $buyer);
        $this->client->loginUser($this->createUser('other@example.com'));
        $this->client->request('POST', '/commandes/'.$order->getId().'/simulation/confirm', ['_token'=>'invalid']); self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($buyer);
        $this->client->request('POST', '/commandes/'.$order->getId().'/simulation/confirm', ['_token'=>'invalid']); self::assertResponseStatusCodeSame(403);
        $this->entityManager->clear(); self::assertSame(StatutCommande::EnAttente, $this->entityManager->find(Commande::class, $order->getId())->getStatut());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        (new DemoMode('prod'))->assertEnabled();
    }
}
