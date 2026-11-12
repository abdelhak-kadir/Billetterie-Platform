<?php

declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\{Commande, User};
use App\Enum\CanalCommande;
use App\Service\TicketingService;
use App\Security\TariffAccess;
use App\Enum\CategorieTarif;

final class SecurityTest extends AdminTestCase
{
    public function testRoleBoundariesAndAnonymousRedirect(): void
    {
        $this->client->request('GET', '/admin'); self::assertResponseIsSuccessful();
        $this->client->loginUser($this->createUser('counter@example.com', 'ROLE_GUICHETIER'));
        $this->client->request('GET', '/admin'); self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/guichet'); self::assertResponseIsSuccessful();
        $this->client->loginUser($this->createUser('client@example.com'));
        $this->client->request('GET', '/guichet'); self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/commandes'); self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/compte'); self::assertResponseIsSuccessful();
        $this->client->restart();
        $this->client->request('GET', '/admin'); self::assertResponseRedirects('http://localhost/connexion');
    }
    public function testPublicRegistrationAndRealLoginCannotChoosePrivilegedRole(): void
    {
        $this->client->restart();
        $crawler = $this->client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer mon compte')->form(['form[email]' => 'new@example.com', 'form[password]' => 'LongPassword2026!']);
        $this->client->submit($form); self::assertResponseRedirects('/connexion');
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'new@example.com']);
        self::assertSame(['ROLE_CLIENT', 'ROLE_USER'], $user->getRoles());
        self::assertNotSame('LongPassword2026!', $user->getPassword());
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Se connecter')->form(['_username' => 'new@example.com', '_password' => 'LongPassword2026!']));
        self::assertResponseRedirects('/compte');
        $this->client->followRedirect(); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin'); self::assertResponseStatusCodeSame(403);
    }
    public function testTariffMatrixIsEnforcedUnderReservationLocks(): void
    {
        $hall = $this->createSalle('Droits', ['Parterre' => ['A' => 1]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall, '2099-12-01T19:00:00Z');
        $reduced = $this->createTarif($rep, 'Réduit', CategorieTarif::Reduit, 8000);
        $client = $this->createUser('buyer@example.com');
        self::assertSame([CategorieTarif::Plein], TariffAccess::categories($client));
        self::assertCount(3, TariffAccess::categories($this->createUser('staff@example.com', 'ROLE_GUICHETIER')));
        self::assertCount(4, TariffAccess::categories($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.com'])));
        try {
            static::getContainer()->get(TicketingService::class)->reserve($rep->getId(), [['inventaire' => $rep->getInventairePlaces()->first()->getId(), 'tarif' => $reduced->getId()]], $client->getEmail(), CanalCommande::EnLigne, $client);
            self::fail('Tarif réduit interdit au client.');
        } catch (\App\Exception\SaleConflict $e) { self::assertStringContainsString('rôle', $e->getMessage()); }
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
    }

    public function testDirectOrderAndTicketAccessRequiresOwnershipOrStaff(): void
    {
        $buyer = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        $hall = $this->createSalle('Propriété', ['Parterre' => ['A' => 1]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall, '2099-12-01T19:00:00Z');
        $tariff = $this->createTarif($rep);
        $sales = static::getContainer()->get(TicketingService::class);
        $order = $sales->reserve($rep->getId(), [['inventaire' => $rep->getInventairePlaces()->first()->getId(), 'tarif' => $tariff->getId()]], $buyer->getEmail(), CanalCommande::EnLigne, $buyer);
        $sales->confirm($order->getId());
        $urls = ['/commandes/'.$order->getId(), '/billets/'.$order->getBillets()->first()->getId()];
        $this->client->loginUser($other);
        foreach ($urls as $url) { $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403); }
        $this->client->loginUser($buyer);
        foreach ($urls as $url) { $this->client->request('GET', $url); self::assertResponseIsSuccessful(); }
        $this->client->loginUser($this->createUser('personnel@example.com', 'ROLE_GUICHETIER'));
        foreach ($urls as $url) { $this->client->request('GET', $url); self::assertResponseIsSuccessful(); }
        $legacy = new Commande('LEGACY-OWNER', $buyer->getEmail(), CanalCommande::EnLigne);
        $this->entityManager->persist($legacy); $this->entityManager->flush();
        $this->client->loginUser($buyer);
        $this->client->request('GET', '/commandes/'.$legacy->getId()); self::assertResponseStatusCodeSame(403);
    }

    public function testRegistrationRejectsRoleInjectionAndInvalidCsrf(): void
    {
        $this->client->restart();
        $crawler = $this->client->request('GET', '/inscription');
        $values = $crawler->selectButton('Créer mon compte')->form(['form[email]' => 'attack@example.com', 'form[password]' => 'LongPassword2026!'])->getPhpValues();
        $values['form']['role'] = 'ROLE_ADMIN';
        $this->client->request('POST', '/inscription', $values);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager->getRepository(User::class)->count(['email' => 'attack@example.com']));
        unset($values['form']['role']); $values['form']['_token'] = 'invalid';
        $this->client->request('POST', '/inscription', $values);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->entityManager->getRepository(User::class)->count(['email' => 'attack@example.com']));
    }

    public function testInvitationIsDeniedToCounterAndAllowedToAdminUnderLocks(): void
    {
        $hall = $this->createSalle('Invitations', ['Parterre'=>['A'=>1]]);
        $rep = $this->createRepresentation($this->createEvenement(), $hall, '2099-12-01T19:00:00Z');
        $tariff = $this->createTarif($rep, 'Invitation', CategorieTarif::Invitation, 0);
        $choices = [['inventaire'=>$rep->getInventairePlaces()->first()->getId(), 'tarif'=>$tariff->getId()]];
        $staff = $this->createUser('invitation-staff@example.com', 'ROLE_GUICHETIER');
        $sales = static::getContainer()->get(TicketingService::class);
        try { $sales->reserve($rep->getId(), $choices, 'guest@example.com', CanalCommande::Guichet, $staff); self::fail('Invitation interdite au guichetier.'); }
        catch (\App\Exception\SaleConflict $e) { self::assertStringContainsString('rôle', $e->getMessage()); }
        self::assertSame(0, $this->entityManager->getRepository(Commande::class)->count([]));
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email'=>'admin@example.com']);
        $order = $sales->reserve($rep->getId(), $choices, 'guest@example.com', CanalCommande::Guichet, $admin);
        $sales->confirm($order->getId());
        self::assertSame(0, $order->getBillets()->first()->getPrixPayeCentimes());
    }
}
