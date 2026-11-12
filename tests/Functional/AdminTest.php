<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Billet;
use App\Entity\Evenement;
use App\Entity\InventairePlace;
use App\Entity\Representation;
use App\Entity\Tarif;
use App\Enum\CategorieTarif;
use App\Repository\InventairePlaceRepository;

final class AdminTest extends AdminTestCase
{
    public function testMainAdminPagesRender(): void
    {
        $salle = $this->createSalle('Salle test', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);
        $tarif = $this->createTarif($representation);
        $commande = $this->createCommande();
        $billet = $this->createBillet('BIL-1', $commande, $representation, $tarif);

        $urls = [
            '/admin',
            '/admin/salles',
            '/admin/salles/'.$salle->getId(),
            '/admin/evenements',
            '/admin/evenements/'.$evenement->getId(),
            '/admin/representations',
            '/admin/representations/'.$representation->getId(),
            '/admin/tarifs',
            '/admin/tarifs/'.$tarif->getId(),
            '/admin/inventaire',
            '/admin/commandes',
            '/admin/commandes/'.$commande->getId(),
            '/admin/billets',
            '/admin/billets/'.$billet->getId(),
        ];

        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertStringContainsString('Billetterie', (string) $this->client->getResponse()->getContent());
        }

        foreach (['/admin/evenements/new', '/admin/representations/new', '/admin/tarifs/new'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertGreaterThan(0, $crawler->filter('input[name$="[_token]"]')->count(), 'Jeton CSRF absent.');
        }
    }

    public function testEventCanBeCreatedAndEdited(): void
    {
        $crawler = $this->client->request('GET', '/admin/evenements/new');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['evenement_form[titre]'] = 'Concert test';
        $form['evenement_form[type]'] = 'concert';
        $form['evenement_form[description]'] = 'Description de test';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $evenement = $this->entityManager->getRepository(Evenement::class)->findOneBy(['titre' => 'Concert test']);
        self::assertInstanceOf(Evenement::class, $evenement);

        $crawler = $this->client->request('GET', '/admin/evenements/'.$evenement->getId().'/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['evenement_form[titre]'] = 'Concert modifié';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $evenement = $this->entityManager->find(Evenement::class, $evenement->getId());
        self::assertSame('Concert modifié', $evenement?->getTitre());
    }

    public function testRepresentationFormCreatesFullInventory(): void
    {
        $plan = [];
        foreach (['Parterre' => range('A', 'O'), 'Balcon' => range('A', 'E')] as $zone => $rangees) {
            $plan[$zone] = array_fill_keys($rangees, 20);
        }

        $salle = $this->createSalle('Salle 400', $plan);
        $evenement = $this->createEvenement();

        $crawler = $this->client->request('GET', '/admin/representations/new');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['representation_form[evenement]'] = (string) $evenement->getId();
        $form['representation_form[salle]'] = (string) $salle->getId();
        $form['representation_form[debut]'] = '2026-12-01T19:00';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $representation = $this->entityManager->getRepository(Representation::class)->findOneBy([]);
        self::assertInstanceOf(Representation::class, $representation);

        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);
        self::assertSame(400, $inventaires->countForRepresentation($representation));
    }

    public function testInvalidRepresentationFormLeavesNoPartialInventory(): void
    {
        $salle = $this->createSalle('Salle invalide', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();

        $crawler = $this->client->request('GET', '/admin/representations/new');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['representation_form[evenement]'] = (string) $evenement->getId();
        $form['representation_form[salle]'] = (string) $salle->getId();
        $form['representation_form[debut]'] = '';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('obligatoires', (string) $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        self::assertSame(0, $this->entityManager->getRepository(Representation::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(InventairePlace::class)->count([]));
    }

    public function testSalleChangeAfterInventoryIsRefused(): void
    {
        $salle = $this->createSalle('Salle A', ['Parterre' => ['A' => 2]]);
        $autreSalle = $this->createSalle('Salle B', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);

        $token = $this->csrfToken('/admin/representations/'.$representation->getId().'/edit', 'input[name="representation_form[_token]"]');
        $this->client->request('POST', '/admin/representations/'.$representation->getId().'/edit', [
            'representation_form' => [
                'evenement' => (string) $evenement->getId(),
                'salle' => (string) $autreSalle->getId(),
                'debut' => '2026-12-01T19:00',
                '_token' => $token,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('ne peut pas être changée', (string) $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        $representation = $this->entityManager->find(Representation::class, $representation->getId());
        self::assertInstanceOf(Representation::class, $representation);
        self::assertSame($salle->getId(), $representation->getSalle()->getId());

        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);
        self::assertSame(2, $inventaires->countForRepresentation($representation));
    }

    public function testNegativeTariffIsRefused(): void
    {
        $salle = $this->createSalle('Salle tarif', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);

        $crawler = $this->client->request('GET', '/admin/tarifs/new');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['tarif_form[representation]'] = (string) $representation->getId();
        $form['tarif_form[libelle]'] = 'Tarif négatif';
        $form['tarif_form[categorie]'] = 'plein';
        $form['tarif_form[prixCentimes]'] = '-10';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('ne peut pas être négatif', (string) $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        self::assertSame(0, $this->entityManager->getRepository(Tarif::class)->count([]));
    }

    public function testEditingTariffKeepsThePaidPriceOfExistingTickets(): void
    {
        $salle = $this->createSalle('Salle tarif modifié', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);
        $tarif = $this->createTarif($representation);
        $commande = $this->createCommande('CMD-TARIF');
        $billet = $this->createBillet('BIL-TARIF', $commande, $representation, $tarif, 10000);

        $crawler = $this->client->request('GET', '/admin/tarifs/'.$tarif->getId().'/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['tarif_form[prixCentimes]'] = '120';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        self::assertSame(12000, $this->entityManager->find(Tarif::class, $tarif->getId())?->getPrixCentimes());
        self::assertSame(10000, $this->entityManager->find(Billet::class, $billet->getId())?->getPrixPayeCentimes());
    }

    public function testUsedTariffCannotBeMovedToAnotherRepresentation(): void
    {
        $salle = $this->createSalle('Salle tarif utilisé', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);
        $autreRepresentation = $this->createRepresentation($evenement, $salle, '2026-12-02T19:00:00Z');
        $tarif = $this->createTarif($representation);
        $commande = $this->createCommande('CMD-UTILISE');
        $this->createBillet('BIL-UTILISE', $commande, $representation, $tarif);

        $token = $this->csrfToken('/admin/tarifs/'.$tarif->getId().'/edit', 'input[name="tarif_form[_token]"]');
        $this->client->request('POST', '/admin/tarifs/'.$tarif->getId().'/edit', [
            'tarif_form' => [
                'representation' => (string) $autreRepresentation->getId(),
                'libelle' => $tarif->getLibelle(),
                'categorie' => $tarif->getCategorie()->value,
                'prixCentimes' => '100',
                '_token' => $token,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('déjà utilisé', (string) $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        self::assertSame($representation->getId(), $this->entityManager->find(Tarif::class, $tarif->getId())?->getRepresentation()->getId());
    }

    public function testInventoryFiltersDistinguishA1ForR1AndR2(): void
    {
        $salle = $this->createSalle('Salle A1', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $r1 = $this->createRepresentation($evenement, $salle, '2026-12-01T19:00:00Z');
        $r2 = $this->createRepresentation($evenement, $salle, '2026-12-02T19:00:00Z');

        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);
        $a1R1 = $inventaires->findOneForRepresentationAndPlace($r1, $this->firstPlace($salle));
        self::assertInstanceOf(InventairePlace::class, $a1R1);

        $commande = $this->createCommande('CMD-A1');
        $a1R1->reserver($commande, new \DateTimeImmutable('+1 hour'));
        $a1R1->vendre();
        $this->entityManager->flush();

        $zoneId = $salle->getZones()->first()->getId();

        $query = http_build_query([
            'representation' => $r1->getId(),
            'zone' => $zoneId,
            'statut' => 'vendue',
            'q' => 'A1',
        ]);
        $crawler = $this->client->request('GET', '/admin/inventaire?'.$query);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.h-seatmap'));
        self::assertGreaterThan(0, $crawler->filter('.h-seatmap .h-seat')->count());
        self::assertCount(1, $crawler->filter('table tbody tr'));
        self::assertStringContainsString('Vendue', $crawler->filter('table tbody tr')->text());

        $query = http_build_query([
            'representation' => $r2->getId(),
            'zone' => $zoneId,
            'statut' => 'disponible',
            'q' => 'A1',
        ]);
        $crawler = $this->client->request('GET', '/admin/inventaire?'.$query);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.h-seatmap'));
        self::assertGreaterThan(0, $crawler->filter('.h-seatmap .h-seat')->count());
        self::assertCount(1, $crawler->filter('table tbody tr'));
        self::assertStringContainsString('Disponible', $crawler->filter('table tbody tr')->text());
    }

    public function testOrdersTicketsAndInventoryAreReadOnly(): void
    {
        $salle = $this->createSalle('Salle lecture', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);
        $tarif = $this->createTarif($representation);
        $commande = $this->createCommande('CMD-LECTURE');
        $billet = $this->createBillet('BIL-LECTURE', $commande, $representation, $tarif);

        $this->client->request('GET', '/admin/commandes/'.$commande->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/billets/'.$billet->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/inventaire');
        self::assertResponseIsSuccessful();

        foreach ([
            '/admin/commandes/'.$commande->getId(),
            '/admin/billets/'.$billet->getId(),
            '/admin/inventaire',
        ] as $url) {
            $this->client->request('POST', $url);
            self::assertContains($this->client->getResponse()->getStatusCode(), [404, 405], $url);
        }

        $this->client->request('GET', '/admin/commandes/'.$commande->getId().'/edit');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/inventaire/new');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvitationMustHaveAZeroPrice(): void
    {
        $salle = $this->createSalle('Salle invitation', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);

        $crawler = $this->client->request('GET', '/admin/tarifs/new');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['tarif_form[representation]'] = (string) $representation->getId();
        $form['tarif_form[libelle]'] = 'Invitation test';
        $form['tarif_form[categorie]'] = 'invitation';
        $form['tarif_form[prixCentimes]'] = '5';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('invitation doit avoir un prix de 0 MAD', (string) $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        self::assertSame(0, $this->entityManager->getRepository(Tarif::class)->count([]));

        $representation = $this->entityManager->find(Representation::class, $representation->getId());
        self::assertInstanceOf(Representation::class, $representation);

        $crawler = $this->client->request('GET', '/admin/tarifs/new');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['tarif_form[representation]'] = (string) $representation->getId();
        $form['tarif_form[libelle]'] = 'Invitation gratuite';
        $form['tarif_form[categorie]'] = 'invitation';
        $form['tarif_form[prixCentimes]'] = '0';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $tarif = $this->entityManager->getRepository(Tarif::class)->findOneBy(['libelle' => 'Invitation gratuite']);
        self::assertInstanceOf(Tarif::class, $tarif);
        self::assertSame(0, $tarif->getPrixCentimes());
    }

    public function testCsrfProtectionIsEnforced(): void
    {
        $salle = $this->createSalle('Salle CSRF', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $representation = $this->createRepresentation($evenement, $salle);

        $this->client->request('POST', '/admin/evenements/new', [
            'evenement_form' => [
                'titre' => 'Événement forgé',
                'type' => 'concert',
                'description' => '',
                '_token' => 'jeton-invalide',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('jeton de sécurité', (string) $this->client->getResponse()->getContent());
        $this->entityManager->clear();
        self::assertSame(
            0,
            $this->entityManager->getRepository(Evenement::class)->count(['titre' => 'Événement forgé']),
        );

        $representation = $this->entityManager->find(Representation::class, $representation->getId());
        self::assertInstanceOf(Representation::class, $representation);
        $tarif = $this->createTarif($representation, 'Tarif CSRF', CategorieTarif::Plein, 10000);

        $this->client->request('POST', '/admin/tarifs/'.$tarif->getId().'/desactiver', ['_token' => 'invalide']);
        self::assertResponseStatusCodeSame(403);
        $this->entityManager->clear();
        self::assertTrue($this->entityManager->find(Tarif::class, $tarif->getId())?->isActif());

        $token = $this->csrfToken('/admin/tarifs/'.$tarif->getId());
        $this->client->request('POST', '/admin/tarifs/'.$tarif->getId().'/desactiver', ['_token' => $token]);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertFalse($this->entityManager->find(Tarif::class, $tarif->getId())?->isActif());
    }
}
