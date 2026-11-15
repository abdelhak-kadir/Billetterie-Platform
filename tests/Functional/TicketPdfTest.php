<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\User;
use App\Enum\CanalCommande;
use App\Service\{TicketCode, TicketingService};

final class TicketPdfTest extends AdminTestCase
{
    public function testPrivatePdfAndSignedQrRespectOwnerStaffAndCancellation(): void
    {
        $owner = $this->createUser('ticket@example.com');
        $hall = $this->createSalle('Salle Horizon', ['Parterre'=>['A'=>1]]);
        $rep = $this->createRepresentation($this->createEvenement('Concert Horizon'), $hall, '2099-12-01T19:00:00Z');
        $tariff = $this->createTarif($rep);
        $sales = static::getContainer()->get(TicketingService::class);
        $order = $sales->reserve($rep->getId(), [['inventaire'=>$rep->getInventairePlaces()->first()->getId(), 'tarif'=>$tariff->getId()]], $owner->getEmail(), CanalCommande::EnLigne, $owner);
        $sales->confirm($order->getId()); $ticket = $order->getBillets()->first();
        $code = static::getContainer()->get(TicketCode::class)->encode($ticket);
        self::assertSame($ticket->getReference(), static::getContainer()->get(TicketCode::class)->reference($code));
        self::assertNull(static::getContainer()->get(TicketCode::class)->reference($code.'x'));
        $url = '/billets/'.$ticket->getId().'/pdf';
        $this->client->loginUser($this->createUser('stranger@example.com'));
        $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($owner);
        $this->client->request('GET', $url); self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringStartsWith('%PDF-', $this->client->getResponse()->getContent());
        self::assertGreaterThan(10000, strlen($this->client->getResponse()->getContent()));
        $this->client->request('GET', '/guichet/controle?'.http_build_query(['code'=>$code])); self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->createUser('pdf-staff@example.com', 'ROLE_GUICHETIER'));
        $this->client->request('GET', $url); self::assertResponseIsSuccessful();
        $crawler = $this->client->request('GET', '/guichet/controle?'.http_build_query(['code'=>$code]));
        self::assertResponseIsSuccessful(); self::assertStringContainsString('Billet valide', $crawler->filter('h1')->text());
        self::assertStringNotContainsString('ticket@example.com', $crawler->text());
        $this->client->request('GET', '/guichet/controle?'.http_build_query(['code'=>$code.'x'])); self::assertResponseStatusCodeSame(404);
        static::getContainer()->get(TicketingService::class)->cancel($order->getId());
        $this->client->request('GET', $url); self::assertResponseStatusCodeSame(410);
        $crawler = $this->client->request('GET', '/guichet/controle?'.http_build_query(['code'=>$code]));
        self::assertResponseIsSuccessful(); self::assertStringContainsString('annulé', $crawler->filter('h1')->text());
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/billets/'.$ticket->getId()); self::assertResponseIsSuccessful();
        self::assertStringContainsString('historique est conservé', $crawler->text());
        self::assertCount(0, $crawler->selectLink('Télécharger le billet PDF'));
    }
}
