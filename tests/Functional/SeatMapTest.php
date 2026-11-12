<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\{Commande, InventairePlace, ReservationItem};
use App\Enum\{CanalCommande, StatutInventairePlace};
use App\Service\TicketingService;

final class SeatMapTest extends AdminTestCase
{
    public function testFullMapHasFourHundredPhysicalSeatsAndIndependentA1States(): void
    {
        $hall = $this->createSalle('Salle 400', [
            'Parterre' => array_fill_keys(range('A', 'O'), 20),
            'Balcon' => array_fill_keys(range('A', 'E'), 20),
        ]);
        $event = $this->createEvenement();
        $r1 = $this->createRepresentation($event, $hall, '2099-12-01T19:00:00Z');
        $r2 = $this->createRepresentation($event, $hall, '2099-12-02T19:00:00Z');
        $tariff = $this->createTarif($r1);
        $inventory = $this->entityManager->getRepository(InventairePlace::class)->findOneForRepresentationAndPlace($r1, $this->firstPlace($hall));
        $service = static::getContainer()->get(TicketingService::class);
        $order = $service->reserve($r1->getId(), [['inventaire' => $inventory->getId(), 'tarif' => $tariff->getId()]], 'demo@example.com', CanalCommande::Guichet);
        $service->confirm($order->getId());
        $crawler = $this->client->request('GET', '/admin/representations/'.$r1->getId().'/plan');
        self::assertResponseIsSuccessful();
        self::assertCount(400, $crawler->filter('[data-seat-id]'));
        self::assertCount(2, $crawler->filter('.h-zone'));
        self::assertStringContainsString('Parterre, rangée A, place 1, Vendue', $crawler->filter('[data-seat-id="'.$inventory->getId().'"]')->attr('aria-label'));
        self::assertSame('vendue', $crawler->filter('[data-seat-id="'.$inventory->getId().'"]')->attr('data-status'));
        self::assertCount(2, $crawler->filter('[data-change-representation]'));
        self::assertCount(1, $crawler->filter('[aria-current="true"]'));
        self::assertStringContainsString('399', $crawler->filter('.h-map-heading')->text());
        $physicalLabels = $crawler->filter('[data-seat-id]')->each(static fn ($seat) => $seat->attr('data-label'));
        $crawler = $this->client->request('GET', '/admin/representations/'.$r2->getId().'/plan');
        self::assertResponseIsSuccessful();
        $a1 = $crawler->filter('[data-label="Parterre, rangée A, place 1"]');
        self::assertSame('disponible', $a1->attr('data-status'));
        self::assertStringEndsWith(', libre', $a1->attr('aria-label'));
        self::assertSame($physicalLabels, $crawler->filter('[data-seat-id]')->each(static fn ($seat) => $seat->attr('data-label')), 'La disposition physique reste stable entre représentations.');
    }

    public function testSelectionTransferPrefillsFormWithoutReservingBeforePost(): void
    {
        $hall = $this->createSalle('Salle transfert', ['Parterre' => ['A' => 3]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall, '2099-12-01T19:00:00Z');
        $tariff = $this->createTarif($rep);
        $ids = array_map(static fn ($i) => $i->getId(), $rep->getInventairePlaces()->toArray());
        $url = '/admin/representations/'.$rep->getId().'/reservation?'.http_build_query(['from_plan' => 1, 'seats' => array_slice($ids, 0, 2)]);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('select[name="form[inventaires][]"] option[selected]'));
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
        $form = $crawler->selectButton('Réserver les places')->form();
        $form['form[email]'] = 'demo@example.com'; $form['form[canal]'] = 'guichet';
        $form['form[tarif]'] = (string) $tariff->getId();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertSame(1, $this->entityManager->getRepository(Commande::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(ReservationItem::class)->count([]));
    }

    public function testConcurrentSeatConflictRedirectsToFreshPlanAndKeepsOnlyFreeSelection(): void
    {
        $hall = $this->createSalle('Salle conflit', ['Parterre' => ['A' => 2]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall, '2099-12-01T19:00:00Z');
        $tariff = $this->createTarif($rep);
        $ids = array_map(static fn ($i) => $i->getId(), $rep->getInventairePlaces()->toArray());
        $crawler = $this->client->request('GET', '/admin/representations/'.$rep->getId().'/reservation?'.http_build_query(['from_plan' => 1, 'seats' => $ids]));
        $form = $crawler->selectButton('Réserver les places')->form();
        $form['form[email]'] = 'first@example.com'; $form['form[canal]'] = 'guichet';
        $form['form[tarif]'] = (string) $tariff->getId();
        static::getContainer()->get(TicketingService::class)->reserve($rep->getId(), [['inventaire' => $ids[0], 'tarif' => $tariff->getId()]], 'other@example.com', CanalCommande::EnLigne);
        $this->client->submit($form);
        self::assertResponseRedirects();
        self::assertStringContainsString('/plan?', $this->client->getResponse()->headers->get('Location'));
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('plan a été actualisé', $crawler->text());
        self::assertSame('reservee', $crawler->filter('[data-seat-id="'.$ids[0].'"]')->attr('data-status'));
        self::assertSame('false', $crawler->filter('[data-seat-id="'.$ids[0].'"]')->attr('data-initial-selected'));
        self::assertSame('true', $crawler->filter('[data-seat-id="'.$ids[1].'"]')->attr('data-initial-selected'));
        $this->entityManager->clear();
        self::assertSame(1, $this->entityManager->getRepository(Commande::class)->count([]));
        self::assertSame(StatutInventairePlace::Disponible, $this->entityManager->find(InventairePlace::class, $ids[1])->getStatut());
    }

    public function testInvalidSelectionAndPlanWritesAreRejected(): void
    {
        $hall = $this->createSalle('Salle validation', ['Parterre' => ['A' => 1]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall, '2099-12-01T19:00:00Z');
        $this->client->request('GET', '/admin/representations/'.$rep->getId().'/plan?seats[]=invalid');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/admin/representations/'.$rep->getId().'/plan');
        self::assertResponseStatusCodeSame(405);
    }

    public function testSelectionCannotImportSeatsFromAnotherRepresentation(): void
    {
        $hall = $this->createSalle('Salle contrôle', ['Parterre' => ['A' => 1]]);
        $event = $this->createEvenement();
        $r1 = $this->createRepresentation($event, $hall, '2099-12-01T19:00:00Z');
        $r2 = $this->createRepresentation($event, $hall, '2099-12-02T19:00:00Z');
        $foreignId = $r2->getInventairePlaces()->first()->getId();
        $query = http_build_query(['from_plan' => 1, 'seats' => [$foreignId]]);
        $this->client->request('GET', '/admin/representations/'.$r1->getId().'/reservation?'.$query);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-initial-selected="true"]'));
        self::assertStringContainsString('disponibilité a changé', $crawler->text());
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
    }

    public function testCancelledRepresentationIsInspectionOnlyWithBlockedSeats(): void
    {
        $hall = $this->createSalle('Salle annulée', ['Parterre' => ['A' => 2]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall, '2099-12-01T19:00:00Z');
        $id = $rep->getInventairePlaces()->first()->getId();
        static::getContainer()->get(TicketingService::class)->cancelRepresentation($rep->getId());
        $crawler = $this->client->request('GET', '/admin/representations/'.$rep->getId().'/plan?'.http_build_query(['mode' => 'select', 'seats' => [$id]]));
        self::assertResponseIsSuccessful();
        self::assertSame('inspect', $crawler->filter('[data-seatmap]')->attr('data-mode'));
        self::assertSame('false', $crawler->filter('[data-seatmap]')->attr('data-selectable'));
        self::assertCount(0, $crawler->filter('.h-modes input[value="select"]'));
        self::assertCount(0, $crawler->filter('[data-initial-selected="true"]'));
        self::assertCount(2, $crawler->filter('[data-status="bloquee"]'));
        self::assertStringContainsString('Bloquée', $crawler->filter('[data-seat-id]')->first()->attr('aria-label'));
    }
}
