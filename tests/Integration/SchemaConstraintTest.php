<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Billet;
use App\Entity\Commande;
use App\Entity\InventairePlace;
use App\Entity\Tarif;
use App\Enum\CanalCommande;
use App\Enum\CategorieTarif;
use App\Repository\InventairePlaceRepository;
use App\Service\RepresentationInventoryService;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;

final class SchemaConstraintTest extends DatabaseTestCase
{
    private RepresentationInventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RepresentationInventoryService(
            $this->entityManager,
            $this->entityManager->getRepository(InventairePlace::class),
        );
    }

    public function testMysqlRejectsANegativeTariffPrice(): void
    {
        $salle = $this->createSalle('Salle SQL', ['Parterre' => ['A' => 1]]);
        $evenement = $this->createEvenement();
        $representation = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-13 20:00:00', new \DateTimeZone('UTC')),
        );

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('chk_tarif_prix_positif');

        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO tarif (representation_id, libelle, categorie, prix_centimes, actif) VALUES (?, ?, ?, ?, ?)',
            [$representation->getId(), 'Prix négatif', CategorieTarif::Plein->value, -1, 1],
        );
    }

    public function testMysqlRejectsDeletingASaleWithTicketsInHistory(): void
    {
        $salle = $this->createSalle('Salle historique', ['Parterre' => ['A' => 1]]);
        $evenement = $this->createEvenement();
        $representation = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-14 20:00:00', new \DateTimeZone('UTC')),
        );
        /** @var InventairePlaceRepository $inventaireRepository */
        $inventaireRepository = $this->entityManager->getRepository(InventairePlace::class);
        $inventaire = $inventaireRepository
            ->findOneForRepresentationAndPlace($representation, $this->firstPlace($salle));
        self::assertInstanceOf(InventairePlace::class, $inventaire);

        $tarif = new Tarif($representation, 'Plein tarif', CategorieTarif::Plein, 10000);
        $commande = new Commande('CMD-0005', 'client@example.test', CanalCommande::Guichet);
        $billet = new Billet('BIL-0007', $commande, $inventaire, $tarif, 10000);
        $this->entityManager->persist($tarif);
        $this->entityManager->persist($commande);
        $this->entityManager->persist($billet);
        $this->entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);

        // Une vente ne peut pas être effacée par une suppression du catalogue.
        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM commande WHERE id = ?',
            [$commande->getId()],
        );
    }
}
