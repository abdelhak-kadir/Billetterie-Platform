<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Billet, Commande, Evenement, InventairePlace, Tarif, User};
use App\Enum\{CanalCommande, CategorieTarif, StatutInventairePlace, TypeEvenement};
use App\Service\{DemoProgram, RepresentationInventoryService, TicketingService};

final class DemoProgramTest extends DatabaseTestCase
{
    public function testSameEveningShowsHaveIndependentInventoryAndTierPrices(): void
    {
        $hall = $this->createSalle('Salle test', ['Parterre'=>['A'=>2,'C'=>2,'F'=>2]]);
        $event = self::getContainer()->get(DemoProgram::class)->create('concert', $hall);
        $shows = $event->getRepresentations()->toArray();
        self::assertCount(2, $shows);
        self::assertSame([DemoProgram::OPENING, DemoProgram::HEADLINER], array_map(static fn ($s)=>$s->getNom(),$shows));
        self::assertSame($shows[0]->getDebut()->format('Y-m-d'), $shows[1]->getDebut()->format('Y-m-d'));
        self::assertLessThan($shows[1]->getDebut(), $shows[0]->getDebut());
        $seat = $this->firstPlace($hall);
        $inventory = $this->entityManager->getRepository(InventairePlace::class)->findOneBy(['representation'=>$shows[0],'place'=>$seat]);
        $other = $this->entityManager->getRepository(InventairePlace::class)->findOneBy(['representation'=>$shows[1],'place'=>$seat]);
        $price = $this->entityManager->getRepository(Tarif::class)->findOneBy(['representation'=>$shows[0],'seatCategory'=>$inventory->getSeatCategory(),'categorie'=>CategorieTarif::Plein]);
        $otherPrice = $this->entityManager->getRepository(Tarif::class)->findOneBy(['representation'=>$shows[1],'seatCategory'=>$other->getSeatCategory(),'categorie'=>CategorieTarif::Plein]);
        self::assertSame('vvip', $inventory->getSeatCategory()->getCode());
        self::assertSame(30000, $price->getPrixCentimes());
        self::assertSame(36000, $otherPrice->getPrixCentimes());
        $sales = self::getContainer()->get(TicketingService::class);
        $order = $sales->reserve($shows[0]->getId(), [['inventaire'=>$inventory->getId(),'tarif'=>$price->getId()]], 'client@example.com', CanalCommande::EnLigne);
        $sales->confirm($order->getId());
        $this->entityManager->refresh($inventory);$this->entityManager->refresh($other);
        self::assertSame(StatutInventairePlace::Vendue,$inventory->getStatut());
        self::assertSame(StatutInventairePlace::Disponible,$other->getStatut());
    }

    public function testRerunPreservesAdministratorChangesAndDoesNotDuplicate(): void
    {
        $hall = $this->createSalle('Salle test', ['Parterre'=>['A'=>2,'C'=>2,'F'=>2]]);
        $program = self::getContainer()->get(DemoProgram::class);
        $event = $program->create('concert',$hall);
        $id = $event->getId();
        $event->modifier('Mon concert personnalisé',TypeEvenement::Concert,'Texte conservé');
        $tariff = $event->getRepresentations()->first()->getTarifs()->first();
        $tariff->changerPrix(45678);
        $this->entityManager->flush();$this->entityManager->clear();
        $same = $program->create('concert',$hall);
        self::assertSame($id,$same->getId());
        self::assertSame('Mon concert personnalisé',$same->getTitre());
        self::assertSame('Texte conservé',$same->getDescription());
        self::assertSame(45678,$same->getRepresentations()->first()->getTarifs()->first()->getPrixCentimes());
        self::assertSame(1,$this->entityManager->getRepository(Evenement::class)->count([]));
        self::assertSame(24,$this->entityManager->getRepository(Tarif::class)->count([]));
    }

    public function testSupplementLeavesLegacySeatsOrdersTicketsAndAccountsUnchanged(): void
    {
        $hall = $this->createSalle('Salle existante', ['Parterre'=>['A'=>2,'C'=>2,'F'=>2]]);
        $old = $this->createEvenement('Ancien concert');
        $show = self::getContainer()->get(RepresentationInventoryService::class)->createRepresentation($old,$hall,new \DateTimeImmutable('2026-12-02T19:00:00Z'));
        $price = new Tarif($show,'Ancien plein tarif',CategorieTarif::Plein,10000);
        $order = new Commande('OLD-ORDER','historique@example.com',CanalCommande::Guichet);
        $user = new User('admin@example.com','ROLE_ADMIN');$user->setPassword('hash-test-opaque');
        foreach ([$price,$order,$user] as $entity) { $this->entityManager->persist($entity); }
        $inventory = $show->getInventairePlaces()->first();
        $inventory->reserver($order,new \DateTimeImmutable('+5 minutes'));
        $ticket = new Billet('OLD-TICKET',$order,$inventory,$price,10000);
        $this->entityManager->persist($ticket);$inventory->vendre();$order->marquerPayee(new \DateTimeImmutable());
        $this->entityManager->flush();
        $db=$this->entityManager->getConnection();$before=[];
        foreach (['salle','zone','place','evenement','representation','inventaire_place','tarif','commande','billet','app_user'] as $table) {
            $before[$table]=$db->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id');
        }
        self::getContainer()->get(DemoProgram::class)->create('concert',$hall);
        foreach ($before as $table=>$rows) {
            self::assertSame($rows,$db->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id LIMIT '.count($rows)),$table);
        }
        self::assertSame(2,$this->entityManager->getRepository(Evenement::class)->count([]));
        self::assertSame(1,$this->entityManager->getRepository(Billet::class)->count([]));
    }
}
