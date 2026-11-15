<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\Evenement;
use App\Entity\InventairePlace;
use App\Entity\Place;
use App\Entity\Representation;
use App\Entity\Salle;
use App\Entity\Tarif;
use App\Entity\Zone;
use App\Entity\User;
use App\Enum\CanalCommande;
use App\Enum\CategorieTarif;
use App\Enum\TypeEvenement;
use App\Repository\InventairePlaceRepository;
use App\Service\RepresentationInventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AdminTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncateTables();
        $this->client->loginUser($this->createUser('admin@example.com', 'ROLE_ADMIN'));
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        self::ensureKernelShutdown();
        parent::tearDown();
    }

    /**
     * @param array<string, array<string, int>> $plan zones => rangées => nombre de places
     */
    protected function createSalle(string $nom, array $plan): Salle
    {
        $salle = new Salle($nom);
        $this->entityManager->persist($salle);

        foreach ($plan as $nomZone => $rangees) {
            $zone = new Zone($salle, $nomZone);
            $salle->addZone($zone);
            $this->entityManager->persist($zone);

            foreach ($rangees as $rangee => $nombreDePlaces) {
                for ($numero = 1; $numero <= $nombreDePlaces; ++$numero) {
                    $place = new Place($zone, (string) $rangee, $numero);
                    $zone->addPlace($place);
                    $this->entityManager->persist($place);
                }
            }
        }

        $this->entityManager->flush();

        return $salle;
    }

    protected function createUser(string $email, string $role = 'ROLE_CLIENT'): User
    {
        $user = new User($email, $role);
        $user->setPassword(static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)->hashPassword($user, 'HorizonDemo2026!'));
        $this->entityManager->persist($user); $this->entityManager->flush();
        return $user;
    }

    protected function createEvenement(string $titre = 'Concert de test'): Evenement
    {
        $evenement = new Evenement($titre, TypeEvenement::Concert);
        $this->entityManager->persist($evenement);
        $this->entityManager->flush();

        return $evenement;
    }

    protected function createRepresentation(Evenement $evenement, Salle $salle, string $debut = '2026-12-01T19:00:00Z'): Representation
    {
        $service = new RepresentationInventoryService(
            $this->entityManager,
            $this->entityManager->getRepository(InventairePlace::class),
        );

        return $service->createRepresentation($evenement, $salle, new \DateTimeImmutable($debut));
    }

    protected function createTarif(
        Representation $representation,
        string $libelle = 'Plein tarif',
        CategorieTarif $categorie = CategorieTarif::Plein,
        int $prixCentimes = 10000,
    ): Tarif {
        $tarif = new Tarif($representation, $libelle, $categorie, $prixCentimes);
        $this->entityManager->persist($tarif);
        $this->entityManager->flush();

        return $tarif;
    }

    protected function createCommande(string $reference = 'CMD-1'): Commande
    {
        $commande = new Commande($reference, 'client@example.com', CanalCommande::Guichet);
        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        return $commande;
    }

    protected function createBillet(
        string $reference,
        Commande $commande,
        Representation $representation,
        Tarif $tarif,
        int $prixCentimes = 10000,
    ): Billet {
        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);
        $inventaire = $inventaires->findOneForRepresentationAndPlace($representation, $this->firstPlace($representation->getSalle()));

        if (! $inventaire instanceof InventairePlace) {
            throw new \RuntimeException('Inventaire de test manquant.');
        }

        $billet = new Billet($reference, $commande, $inventaire, $tarif, $prixCentimes);
        $this->entityManager->persist($billet);
        $this->entityManager->flush();

        return $billet;
    }

    protected function firstPlace(Salle $salle): Place
    {
        foreach ($salle->getZones() as $zone) {
            $place = $zone->getPlaces()->first();

            if ($place instanceof Place) {
                return $place;
            }
        }

        throw new \RuntimeException('La salle ne contient aucune place.');
    }

    protected function csrfToken(string $url, string $selector = 'input[name="_token"]'): string
    {
        $crawler = $this->client->request('GET', $url);
        $node = $crawler->filter($selector);
        self::assertGreaterThan(0, $node->count(), 'Jeton CSRF introuvable.');

        return (string) $node->attr('value');
    }

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
        if (!in_array($connection->fetchOne('SELECT DATABASE()'), ['billetterie_test', 'billetterie_s2_test'], true)) {
            throw new \RuntimeException('Refus de vider une base non dédiée aux tests.');
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['reservation_item', 'billet', 'inventaire_place', 'tarif', 'commande', 'representation', 'place', 'zone', 'salle', 'evenement', 'app_user'] as $table) {
            $connection->executeStatement(sprintf('TRUNCATE TABLE %s', $table));
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }
}
