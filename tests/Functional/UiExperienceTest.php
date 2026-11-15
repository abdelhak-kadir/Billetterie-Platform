<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Controller\ErrorController;
use App\Entity\Evenement;
use App\Service\EventImageStorage;

final class UiExperienceTest extends AdminTestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) { if (is_file($path)) { unlink($path); } }
        parent::tearDown();
    }

    public function testEmptyFilterOptionsAndPageNumbersAreValidSearches(): void
    {
        $hall = $this->createSalle('Salle filtres', ['Parterre' => ['A' => 2]]);
        $event = $this->createEvenement('Événement filtre vide');
        $this->createTarif($this->createRepresentation($event, $hall));
        foreach ([
            '/admin/representations?evenement=&salle=&page=',
            '/admin/tarifs?representation=&actif=&q=&page=',
            '/admin/inventaire?representation=&zone=&statut=&q=&page=',
            '/admin/billets?representation=&statut=&q=&page=',
            '/admin/indicateurs?representation=&zone=&canal=',
            '/admin/evenements?type=&q=&page=',
            '/admin/commandes?canal=&statut=&q=&page=',
            '/admin/salles?page=',
            '/admin/representations?evenement=invalid&salle[]=1&page=-1',
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertStringNotContainsString('Input value', $this->client->getResponse()->getContent());
        }
        $this->client->request('GET', '/admin/evenements?type=&q=filtre');
        self::assertSelectorCount(1, '.poster-link');
        self::assertSelectorTextContains('.poster-caption', 'Événement filtre vide');
    }

    public function testUnknownPagesAndMissingEventsHaveAFriendly404InDebugMode(): void
    {
        $this->client->restart();
        foreach (['/page-qui-nexiste-pas', '/evenements/999999', '/media/events/'.str_repeat('a', 32).'.png'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
            self::assertSelectorTextContains('h1', 'Cette page est introuvable.');
            self::assertSelectorExists('a[href="/"]');
            self::assertStringNotContainsString('Symfony Exception', $this->client->getResponse()->getContent());
            self::assertStringNotContainsString('Stack Trace', $this->client->getResponse()->getContent());
        }
    }

    public function testMalformedSearchKeepsItsStatusWithoutExposingTechnicalDetails(): void
    {
        $this->client->request('GET', '/?q[]=invalide');
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorTextContains('h1', 'Votre demande n’a pas pu être traitée.');
        self::assertStringNotContainsString('non-scalar', $this->client->getResponse()->getContent());
    }

    public function testServerErrorsNeverDisplayThePrivateExceptionMessage(): void
    {
        $response = static::getContainer()->get(ErrorController::class)->show(new \RuntimeException('PRIVATE_DATABASE_DETAILS'));
        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Le service est momentanément indisponible.', $response->getContent());
        self::assertStringNotContainsString('PRIVATE_DATABASE_DETAILS', $response->getContent());
    }

    public function testEventImageIsPublishedRetainedAndReplaced(): void
    {
        $crawler = $this->client->request('GET', '/admin/evenements/new');
        $form = $crawler->selectButton('Enregistrer')->form(['evenement_form[titre]' => 'Avec visuel', 'evenement_form[type]' => 'concert']);
        $form['evenement_form[image]']->upload($this->imageFile('concert.webp'));
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        $event = $this->entityManager->getRepository(Evenement::class)->findOneBy(['titre' => 'Avec visuel']);
        self::assertInstanceOf(Evenement::class, $event);
        $id = $event->getId(); $original = $event->getImageFilename();
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.webp$/', $original);
        $this->temporaryFiles[] = static::getContainer()->get(EventImageStorage::class)->path($original);
        $this->client->request('GET', '/media/events/'.$original);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $this->client->request('GET', '/evenements/'.$id);
        self::assertSelectorExists('img[src="/media/events/'.$original.'"]');

        $crawler = $this->client->request('GET', '/admin/evenements/'.$id.'/edit');
        $this->client->submit($crawler->selectButton('Enregistrer')->form());
        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertSame($original, $this->entityManager->find(Evenement::class, $id)->getImageFilename());

        $crawler = $this->client->request('GET', '/admin/evenements/'.$id.'/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['evenement_form[image]']->upload($this->imageFile('theatre.webp'));
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        $replacement = $this->entityManager->find(Evenement::class, $id)->getImageFilename();
        self::assertNotSame($original, $replacement);
        $this->temporaryFiles[] = static::getContainer()->get(EventImageStorage::class)->path($replacement);
        self::assertFileDoesNotExist(static::getContainer()->get(EventImageStorage::class)->path($original));
        $this->client->request('GET', '/media/events/'.$replacement);
        self::assertResponseIsSuccessful();
    }

    public function testInvalidOrOversizedImagesLeaveTheExistingEventUntouched(): void
    {
        $event = $this->createEvenement('Visuel protégé');
        foreach (['false.png' => 'not an image', 'large.png' => str_repeat('x', 2000001)] as $name => $content) {
            $path = sys_get_temp_dir().'/horizon-'.bin2hex(random_bytes(8)).'-'.$name;
            file_put_contents($path, $content); $this->temporaryFiles[] = $path;
            $crawler = $this->client->request('GET', '/admin/evenements/'.$event->getId().'/edit');
            $form = $crawler->selectButton('Enregistrer')->form(['evenement_form[titre]' => 'Ne doit pas être sauvegardé']);
            $form['evenement_form[image]']->upload($path);
            $this->client->submit($form);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('#evenement_form_image[aria-invalid="true"]');
            self::assertStringNotContainsString('Symfony Exception', $this->client->getResponse()->getContent());
            $this->entityManager->clear();
            $current = $this->entityManager->find(Evenement::class, $event->getId());
            self::assertSame('Visuel protégé', $current->getTitre());
            self::assertNull($current->getImageFilename());
        }
    }

    public function testAnImageDisguisedAsPhpIsRejectedAndClientsCannotUpload(): void
    {
        $path = sys_get_temp_dir().'/horizon-'.bin2hex(random_bytes(8)).'.php';
        copy(dirname(__DIR__, 2).'/public/admin/media/concert.webp', $path);$this->temporaryFiles[] = $path;
        $crawler = $this->client->request('GET', '/admin/evenements/new');
        $form = $crawler->selectButton('Enregistrer')->form(['evenement_form[titre]' => 'Fichier interdit', 'evenement_form[type]' => 'concert']);
        $form['evenement_form[image]']->upload($path);$this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#evenement_form_image[aria-invalid="true"]');
        self::assertSame(0, $this->entityManager->getRepository(Evenement::class)->count(['titre' => 'Fichier interdit']));
        $this->client->loginUser($this->createUser('client-upload@example.com'));
        $this->client->request('POST', '/admin/evenements/new');
        self::assertResponseStatusCodeSame(403);
    }

    private function imageFile(string $name): string
    {
        $path = sys_get_temp_dir().'/horizon-'.bin2hex(random_bytes(8)).'.webp';
        copy(dirname(__DIR__, 2).'/public/admin/media/'.$name, $path);
        $this->temporaryFiles[] = $path;
        return $path;
    }
}
