<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Evenement;
use App\Entity\Place;
use App\Entity\Salle;
use App\Entity\Zone;
use App\Enum\TypeEvenement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->truncateTables();
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

    protected function createEvenement(string $titre = 'Concert de démonstration'): Evenement
    {
        $evenement = new Evenement($titre, TypeEvenement::Concert);
        $this->entityManager->persist($evenement);
        $this->entityManager->flush();

        return $evenement;
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

    private function truncateTables(): void
    {
        $connection = $this->entityManager->getConnection();
        if (!in_array($connection->fetchOne('SELECT DATABASE()'), ['billetterie_test', 'billetterie_s2_test'], true)) {
            throw new \RuntimeException('Refus de vider une base non dédiée aux tests.');
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['reservation_item', 'billet', 'inventaire_place', 'tarif', 'commande', 'representation', 'place', 'zone', 'salle', 'evenement'] as $table) {
            $connection->executeStatement(sprintf('TRUNCATE TABLE %s', $table));
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }
}
