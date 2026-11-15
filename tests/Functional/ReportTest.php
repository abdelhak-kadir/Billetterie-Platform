<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Enum\{CanalCommande, CategorieTarif};
use App\Service\{SalesReport, TicketingService};
final class ReportTest extends AdminTestCase
{
    public function testHistoricalPricesRefundsInvitationsResalesAndCapacity(): void
    {
        $hall = $this->createSalle('Indicateurs', ['Parterre'=>['A'=>3], 'Balcon'=>['A'=>1]]);
        $event = $this->createEvenement();
        $r1 = $this->createRepresentation($event, $hall, '2099-12-01T19:00:00Z');
        $r2 = $this->createRepresentation($event, $hall, '2099-12-02T19:00:00Z');
        $plein = $this->createTarif($r1); $reduced = $this->createTarif($r1, 'Réduit', CategorieTarif::Reduit, 8000);
        $invitation = $this->createTarif($r1, 'Invitation', CategorieTarif::Invitation, 0);
        $ids = array_map(static fn ($i) => $i->getId(), $r1->getInventairePlaces()->toArray());
        $sales = static::getContainer()->get(TicketingService::class);
        $first = $sales->reserve($r1->getId(), [['inventaire'=>$ids[0], 'tarif'=>$plein->getId()]], 'demo@example.com', CanalCommande::EnLigne); $sales->confirm($first->getId());
        $sales->cancel($first->getId()); $plein->changerPrix(12000); $this->entityManager->flush();
        foreach ([[$ids[0], $plein, CanalCommande::EnLigne], [$ids[1], $reduced, CanalCommande::Guichet], [$ids[2], $invitation, CanalCommande::Guichet]] as [$id,$tariff,$channel]) {
            $order=$sales->reserve($r1->getId(), [['inventaire'=>$id,'tarif'=>$tariff->getId()]], 'demo@example.com', $channel); $sales->confirm($order->getId());
        }
        $report = static::getContainer()->get(SalesReport::class);
        $data = $report->build($r1->getId());
        self::assertSame(['gross'=>30000,'refunded'=>10000,'net'=>20000,'sold'=>3,'capacity'=>4,'percent'=>75.0], $data['totals']);
        self::assertSame(100.0, $data['occupancy'][0]['percent']);
        self::assertSame(0.0, $data['occupancy'][1]['percent']);
        $tariffs = array_column($data['revenue'], null, 'tariff_id');
        self::assertSame(22000, $tariffs[$plein->getId()]['gross']);
        self::assertSame(2, $tariffs[$plein->getId()]['issued']);
        self::assertSame(1, $tariffs[$plein->getId()]['valid']);
        self::assertSame(1, $tariffs[$invitation->getId()]['issued']); self::assertSame(0, $tariffs[$invitation->getId()]['gross']);
        self::assertSame(0, $report->build($r2->getId())['totals']['sold']);
        self::assertSame(4, $report->build($r2->getId())['totals']['capacity']);
        $counter = $report->build($r1->getId(), 0, 'guichet');
        self::assertSame(8000, $counter['totals']['net']); self::assertSame(50.0, $counter['totals']['percent']); self::assertSame(4, $counter['totals']['capacity']);
        $parterre = $report->build($r1->getId(), $hall->getZones()->first()->getId());
        self::assertSame(3, $parterre['totals']['capacity']); self::assertSame(100.0, $parterre['totals']['percent']);
        $crawler = $this->client->request('GET', '/admin/indicateurs?representation='.$r1->getId());
        self::assertResponseIsSuccessful(); self::assertSame('300,00 MAD', $crawler->filter('[data-report-gross]')->text());
        self::assertSame('200,00 MAD', $crawler->filter('[data-report-net]')->text());
        static::getContainer()->get(TicketingService::class)->cancelRepresentation($r1->getId());
        $after = static::getContainer()->get(SalesReport::class)->build($r1->getId());
        self::assertSame(30000, $after['totals']['gross']); self::assertSame(30000, $after['totals']['refunded']);
        self::assertSame(0, $after['totals']['net']); self::assertSame(0, $after['totals']['sold']); self::assertSame(4, $after['totals']['capacity']);
        $this->client->loginUser($this->createUser('staff-report@example.com', 'ROLE_GUICHETIER'));
        $this->client->request('GET', '/admin/indicateurs'); self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->createUser('client-report@example.com'));
        $this->client->request('GET', '/admin/indicateurs'); self::assertResponseStatusCodeSame(403);
    }
}
