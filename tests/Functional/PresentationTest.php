<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class PresentationTest extends AdminTestCase
{
    public function testDateSelectionUsesOnlyExistingDatesAndSurvivesDetailReturn(): void
    {
        $salle = $this->createSalle('Salle dates', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $this->createRepresentation($evenement, $salle, '2099-12-01T19:00:00Z');
        $r2 = $this->createRepresentation($evenement, $salle, '2099-12-02T19:00:00Z');
        $url = '/admin/evenements/'.$evenement->getId();

        $crawler = $this->client->request('GET', $url.'?date=2099-12-02');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.date-nav a'));
        self::assertCount(1, $crawler->filter('.date-nav a[aria-current="date"]'));
        self::assertStringContainsString('2099-12-02', $crawler->filter('.date-nav a[aria-current="date"]')->attr('href'));
        self::assertSame('/admin/representations/'.$r2->getId().'/plan', $crawler->filter('.session')->attr('href'));
        self::assertCount(1, $crawler->filter('.session'));
        self::assertStringContainsString('2 places disponibles', $crawler->filter('.session')->text());

        $crawler = $this->client->request('GET', '/admin/representations/'.$r2->getId());
        $return = $crawler->filter('.back-link')->attr('href');
        self::assertStringContainsString('date=2099-12-02', $return);
        $crawler = $this->client->request('GET', $return);
        self::assertStringContainsString('2099-12-02', $crawler->filter('[aria-current="date"]')->attr('href'));

        $crawler = $this->client->request('GET', $url.'?date=invalid');
        self::assertCount(2, $crawler->filter('.date-nav a'));
        self::assertStringContainsString('2099-12-01', $crawler->filter('[aria-current="date"]')->attr('href'));
    }

    public function testNewEventUsesFallbackAndNextDateComesFromFutureProgramme(): void
    {
        $salle = $this->createSalle('Salle catalogue', ['Parterre' => ['A' => 1]]);
        $evenement = $this->createEvenement('Événement sans visuel');
        $this->createRepresentation($evenement, $salle, '2020-01-01T19:00:00Z');
        $this->createRepresentation($evenement, $salle, '2099-12-01T19:00:00Z');
        $crawler = $this->client->request('GET', '/admin/evenements?q=sans%20visuel');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.poster-link'));
        self::assertSame('/admin/media/fallback.svg', $crawler->filter('.poster img')->attr('src'));
        self::assertStringContainsString('01/12/2099', $crawler->filter('.poster-caption')->text());
        self::assertStringNotContainsString('2020', $crawler->filter('.poster-caption')->text());
    }

    public function testEmptyStatesAndEventWithoutRepresentationsRemainUsable(): void
    {
        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucun événement', $crawler->text());
        $evenement = $this->createEvenement('Sans programmation');
        $crawler = $this->client->request('GET', '/admin/evenements/'.$evenement->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.date-nav a'));
        self::assertStringContainsString('Aucune représentation', $crawler->text());
        $crawler = $this->client->request('GET', '/admin/evenements?q=introuvable');
        self::assertCount(0, $crawler->filter('.poster-link'));
        self::assertStringContainsString('Aucun événement pour ces filtres', $crawler->text());
    }
}
