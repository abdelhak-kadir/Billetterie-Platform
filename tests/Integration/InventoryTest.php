<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\InventairePlace;
use App\Entity\Place;
use App\Entity\Representation;
use App\Enum\StatutInventairePlace;
use App\Repository\InventairePlaceRepository;
use App\Service\RepresentationInventoryService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class InventoryTest extends DatabaseTestCase
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

    public function testSamePlaceHasTwoIndependentInventoriesForTwoRepresentations(): void
    {
        $salle = $this->createSalle('Salle principale', ['Parterre' => ['A' => 2]]);
        $evenement = $this->createEvenement();
        $place = $this->firstPlace($salle);

        $representation1 = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-01 20:00:00', new \DateTimeZone('UTC')),
        );
        $representation2 = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-02 20:00:00', new \DateTimeZone('UTC')),
        );

        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class)->findBy(['place' => $place]);

        self::assertCount(2, $inventaires);
        self::assertNotSame(
            $inventaires[0]->getRepresentation()->getId(),
            $inventaires[1]->getRepresentation()->getId(),
        );
        self::assertContains(
            $representation1->getId(),
            array_map(static fn (InventairePlace $i): ?int => $i->getRepresentation()->getId(), $inventaires),
        );
        self::assertContains(
            $representation2->getId(),
            array_map(static fn (InventairePlace $i): ?int => $i->getRepresentation()->getId(), $inventaires),
        );

        foreach ($inventaires as $inventaire) {
            self::assertSame(StatutInventairePlace::Disponible, $inventaire->getStatut());
        }
    }

    public function testRepresentationReceivesEverySeatOfItsSalle(): void
    {
        $salle = $this->createSalle('Salle variable', [
            'Parterre' => ['A' => 3, 'B' => 2],
            'Balcon' => ['C' => 4],
        ]);
        $evenement = $this->createEvenement();

        $representation = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-03 20:00:00', new \DateTimeZone('UTC')),
        );

        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);
        $count = $inventaires->countForRepresentation($representation);

        self::assertSame(9, $count);
        self::assertSame($salle->getCapacite(), $count);
    }

    public function testRepresentationReceivesFourHundredSeatsWhenSalleHasFourHundred(): void
    {
        $plan = [];

        foreach (['Parterre' => range('A', 'O'), 'Balcon' => range('A', 'E')] as $zone => $rangees) {
            $plan[$zone] = array_fill_keys($rangees, 20);
        }

        $salle = $this->createSalle('Salle 400 places', $plan);
        $evenement = $this->createEvenement();

        self::assertSame(400, $salle->getCapacite());

        $representation = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-03 21:00:00', new \DateTimeZone('UTC')),
        );

        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);

        self::assertSame(400, $inventaires->countForRepresentation($representation));
    }

    public function testInventoryRejectsAPlaceFromAnotherSalle(): void
    {
        $salleA = $this->createSalle('Salle A', ['Parterre' => ['A' => 1]]);
        $salleB = $this->createSalle('Salle B', ['Parterre' => ['A' => 1]]);
        $evenement = $this->createEvenement();

        $representationA = $this->service->createRepresentation(
            $evenement,
            $salleA,
            new \DateTimeImmutable('2026-12-04 20:00:00', new \DateTimeZone('UTC')),
        );
        $placeB = $this->firstPlace($salleB);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La place n’appartient pas à la salle de la représentation.');

        new InventairePlace($representationA, $placeB);
    }

    public function testDuplicateRepresentationPlaceIsRejectedByMysql(): void
    {
        $salle = $this->createSalle('Salle unique', ['Parterre' => ['A' => 1]]);
        $evenement = $this->createEvenement();
        $representation = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-05 20:00:00', new \DateTimeZone('UTC')),
        );
        $place = $this->firstPlace($salle);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO inventaire_place (representation_id, place_id, statut) VALUES (?, ?, ?)',
            [$representation->getId(), $place->getId(), StatutInventairePlace::Disponible->value],
        );
    }

    public function testRepeatedInventoryInitializationDoesNotDuplicateRows(): void
    {
        $salle = $this->createSalle('Salle répétable', ['Parterre' => ['A' => 2, 'B' => 1]]);
        $evenement = $this->createEvenement();
        $representation = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-06 20:00:00', new \DateTimeZone('UTC')),
        );

        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);

        self::assertSame(3, $inventaires->countForRepresentation($representation));
        self::assertSame(3, $this->service->initializeInventory($representation));
        self::assertSame(3, $inventaires->countForRepresentation($representation));
    }

    public function testGenerationErrorLeavesNoPartialInventory(): void
    {
        $salle = $this->createSalle('Salle en erreur', ['Parterre' => ['A' => 3]]);
        $evenement = $this->createEvenement();
        /** @var InventairePlaceRepository $inventaires */
        $inventaires = $this->entityManager->getRepository(InventairePlace::class);

        $failingService = new class($this->entityManager, $inventaires) extends RepresentationInventoryService {
            private int $calls = 0;

            protected function createInventoryPlace(Representation $representation, Place $place): InventairePlace
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('Échec simulé pendant la génération.');
                }

                return parent::createInventoryPlace($representation, $place);
            }
        };

        try {
            $failingService->createRepresentation(
                $evenement,
                $salle,
                new \DateTimeImmutable('2026-12-07 20:00:00', new \DateTimeZone('UTC')),
            );
            self::fail('La génération aurait dû échouer.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Échec simulé pendant la génération.', $exception->getMessage());
        }

        self::assertSame(0, $this->entityManager->getRepository(Representation::class)->count([]));
        self::assertSame(0, $inventaires->count([]));
    }
}
