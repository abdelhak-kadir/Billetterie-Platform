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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class BilletTest extends DatabaseTestCase
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

    public function testChangingTariffDoesNotChangeThePaidPriceOfAnIssuedTicket(): void
    {
        [$representation, $inventaire, $place] = $this->createRepresentationWithOneSeat();
        self::assertSame($representation->getSalle()->getId(), $place->getZone()->getSalle()->getId());

        $tarif = new Tarif($representation, 'Plein tarif', CategorieTarif::Plein, 10000);
        $commande = new Commande('CMD-0001', 'client@example.test', CanalCommande::Guichet);
        $billet = new Billet('BIL-0001', $commande, $inventaire, $tarif, 10000);

        $this->entityManager->persist($tarif);
        $this->entityManager->persist($commande);
        $this->entityManager->persist($billet);
        $this->entityManager->flush();

        $tarif->changerPrix(12000);
        $this->entityManager->flush();
        $this->entityManager->refresh($billet);

        self::assertSame(12000, $tarif->getPrixCentimes());
        self::assertSame(10000, $billet->getPrixPayeCentimes());
    }

    public function testTariffMustBelongToTheSameRepresentationAsTheInventoryLine(): void
    {
        $salle = $this->createSalle('Salle tarifs', ['Parterre' => ['A' => 1]]);
        $evenement = $this->createEvenement();
        $representation1 = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-10 20:00:00', new \DateTimeZone('UTC')),
        );
        $representation2 = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-11 20:00:00', new \DateTimeZone('UTC')),
        );

        /** @var InventairePlaceRepository $inventaireRepository */
        $inventaireRepository = $this->entityManager->getRepository(InventairePlace::class);
        $inventaire1 = $inventaireRepository
            ->findOneForRepresentationAndPlace($representation1, $this->firstPlace($salle));
        self::assertNotNull($inventaire1);

        $tarif2 = new Tarif($representation2, 'Tarif autre séance', CategorieTarif::Plein, 10000);
        $commande = new Commande('CMD-0002', 'client@example.test', CanalCommande::Guichet);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le tarif ne concerne pas la représentation du billet.');

        new Billet('BIL-0002', $commande, $inventaire1, $tarif2, 10000);
    }

    public function testOnlyOneValidTicketPerInventoryWhileKeepingCancelledHistory(): void
    {
        [$representation, $inventaire] = $this->createRepresentationWithOneSeat();

        $tarif = new Tarif($representation, 'Plein tarif', CategorieTarif::Plein, 10000);
        $commande = new Commande('CMD-0003', 'client@example.test', CanalCommande::Guichet);
        $this->entityManager->persist($tarif);
        $this->entityManager->persist($commande);

        $premierBillet = new Billet('BIL-0003', $commande, $inventaire, $tarif, 10000);
        $this->entityManager->persist($premierBillet);
        $this->entityManager->flush();

        $premierBillet->annuler();
        $this->entityManager->flush();

        self::assertNull($premierBillet->getInventaireValideId());

        $billetDeRevente = new Billet('BIL-0004', $commande, $inventaire, $tarif, 10000);
        $this->entityManager->persist($billetDeRevente);
        $this->entityManager->flush();

        self::assertCount(2, $this->entityManager->getRepository(Billet::class)->findBy(['inventairePlace' => $inventaire]));
        self::assertNotNull($billetDeRevente->getInventaireValideId());

        $secondBilletValide = new Billet('BIL-0005', $commande, $inventaire, $tarif, 10000);
        $this->entityManager->persist($secondBilletValide);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testNegativeTicketPriceIsRejectedByTheEntity(): void
    {
        [$representation, $inventaire] = $this->createRepresentationWithOneSeat();
        $tarif = new Tarif($representation, 'Invitation', CategorieTarif::Invitation, 0);
        $commande = new Commande('CMD-0004', 'client@example.test', CanalCommande::EnLigne);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix payé d’un billet ne peut pas être négatif.');

        new Billet('BIL-0006', $commande, $inventaire, $tarif, -1);
    }

    /**
     * @return array{Representation, InventairePlace, \App\Entity\Place}
     */
    private function createRepresentationWithOneSeat(): array
    {
        $salle = $this->createSalle('Salle billet', ['Parterre' => ['A' => 1]]);
        $evenement = $this->createEvenement();
        $representation = $this->service->createRepresentation(
            $evenement,
            $salle,
            new \DateTimeImmutable('2026-12-12 20:00:00', new \DateTimeZone('UTC')),
        );
        $place = $this->firstPlace($salle);
        /** @var InventairePlaceRepository $inventaireRepository */
        $inventaireRepository = $this->entityManager->getRepository(InventairePlace::class);
        $inventaire = $inventaireRepository
            ->findOneForRepresentationAndPlace($representation, $place);

        if (! $inventaire instanceof InventairePlace) {
            throw new \RuntimeException('La ligne d’inventaire n’a pas été créée.');
        }

        return [$representation, $inventaire, $place];
    }
}
