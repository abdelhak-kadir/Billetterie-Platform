<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Billet, Commande, InventairePlace, ReservationItem, Tarif};
use App\Enum\{CanalCommande, CategorieTarif, StatutBillet, StatutCommande, StatutInventairePlace, StatutRemboursement};
use App\Exception\SaleConflict;
use App\Service\{RepresentationInventoryService, TicketingService};
use App\Tests\Support\FrozenClock;

abstract class TicketingTestCase extends DatabaseTestCase
{
    protected FrozenClock $clock;
    protected TicketingService $sales;
    protected int $r1;
    protected int $r2;
    protected array $inventories;
    protected int $tariff;
    protected int $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2030-01-01T12:00:00Z'));
        $this->sales = new TicketingService($this->entityManager, $this->clock);
        $hall = $this->createSalle('Salle Sprint 2', ['Parterre' => ['A' => 3]]);
        $event = $this->createEvenement();
        $service = self::getContainer()->get(RepresentationInventoryService::class);
        $r1 = $service->createRepresentation($event, $hall, new \DateTimeImmutable('2030-02-01T19:00:00Z'));
        $r2 = $service->createRepresentation($event, $hall, new \DateTimeImmutable('2030-02-02T19:00:00Z'));
        $this->r1 = $r1->getId(); $this->r2 = $r2->getId();
        $tariff = new Tarif($r1, 'Plein', CategorieTarif::Plein, 10000);
        $invitation = new Tarif($r1, 'Invitation', CategorieTarif::Invitation, 0);
        $this->entityManager->persist($tariff); $this->entityManager->persist($invitation);
        $this->entityManager->flush();
        $this->tariff = $tariff->getId(); $this->invitation = $invitation->getId();
        $this->inventories = array_map(static fn ($i) => $i->getId(), $this->entityManager->getRepository(InventairePlace::class)->findBy(['representation' => $r1], ['id' => 'ASC']));
    }

    protected function reserve(array $indices = [0], ?int $tariff = null): Commande
    {
        return $this->sales->reserve($this->r1, array_map(fn ($i) => ['inventaire' => $this->inventories[$i], 'tarif' => $tariff ?? $this->tariff], $indices), 'client@example.com', CanalCommande::EnLigne);
    }

}
