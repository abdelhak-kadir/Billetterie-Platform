<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use App\Entity\{Evenement,Representation,SeatCategory,InventairePlace,Tarif,Commande};
use App\Service\{EventSetupService,TicketingService};
use App\Enum\CanalCommande;
use App\Exception\SaleConflict;

final class EventWorkspaceTest extends AdminTestCase
{
    private function createProgramme(int $shows = 2): array
    {
        $hall = $this->createSalle('Salle des spectacles',['Parterre'=>['A'=>2,'B'=>2,'C'=>2]]);
        $crawler = $this->client->request('GET','/admin/evenements/creer');
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Créer l’événement et ses spectacles')->form()->getPhpValues();
        $values['event_setup']['evenement']=['titre'=>'Festival Horizon','type'=>'concert','description'=>'Deux spectacles à choisir.'];
        $values['event_setup']['hall']=(string)$hall->getId();
        $values['event_setup']['shows']=[];
        for($i=0;$i<$shows;$i++) {
            $values['event_setup']['shows'][]=['nom'=>$i===0?'Concert principal':'Session acoustique','debut'=>'2099-12-0'.($i+1).'T20:00','standardPrice'=>(string)(100+20*$i),'vipPrice'=>'200','vvipPrice'=>'300'];
        }
        $zone=$hall->getZones()->first();
        $values['event_setup']['rowCategories']=[EventSetupService::rowKey($zone->getId(),'A')=>'vvip',EventSetupService::rowKey($zone->getId(),'B')=>'vip',EventSetupService::rowKey($zone->getId(),'C')=>'standard'];
        return [$hall,$values];
    }

    private function savedProgramme(): array
    {
        [$hall,$values]=$this->createProgramme();
        $this->client->request('POST','/admin/evenements/creer',$values);
        self::assertResponseRedirects();
        $this->entityManager=static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $this->entityManager->clear();
        $event=$this->entityManager->getRepository(Evenement::class)->findOneBy(['titre'=>'Festival Horizon']);
        self::assertInstanceOf(Evenement::class,$event);
        return [$event,$this->entityManager->getRepository(Representation::class)->findBy(['evenement'=>$event],['debut'=>'ASC'])];
    }

    public function testOneFormCreatesNamedShowsInventoriesCategoriesAndPrices(): void
    {
        [$event,$shows]=$this->savedProgramme();
        self::assertCount(2,$shows);
        self::assertSame('Concert principal',$shows[0]->getNom());self::assertSame('Session acoustique',$shows[1]->getNom());
        foreach($shows as $show) {
            self::assertSame(6,$this->entityManager->getRepository(InventairePlace::class)->count(['representation'=>$show]));
            self::assertSame(3,$this->entityManager->getRepository(SeatCategory::class)->count(['representation'=>$show]));
            self::assertCount(3,$show->getTarifs());
            foreach($show->getInventairePlaces() as $seat) {
                self::assertNotNull($seat->getSeatCategory());
                self::assertSame(['A'=>'vvip','B'=>'vip','C'=>'standard'][$seat->getPlace()->getRangee()],$seat->getSeatCategory()->getCode());
            }
        }
        $this->client->request('GET','/evenements/'.$event->getId());self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.sessions','Concert principal');self::assertSelectorTextContains('.sessions','Session acoustique');
        $this->client->request('GET','/admin/evenements/'.$event->getId());self::assertSelectorCount(2,'.show-card');
        $this->client->request('GET','/admin');self::assertSelectorCount(1,'.event-card');
    }

    public function testInvalidSecondShowLeavesNoPartialEventOrInventory(): void
    {
        [$hall,$values]=$this->createProgramme();$values['event_setup']['shows'][1]['vipPrice']='-1';
        $this->client->request('POST','/admin/evenements/creer',$values);self::assertResponseIsSuccessful();
        foreach([Evenement::class,Representation::class,SeatCategory::class,InventairePlace::class,Tarif::class] as $class) { self::assertSame(0,$this->entityManager->getRepository($class)->count([])); }
    }

    public function testForeignRowAndRoleInjectionCannotCreateAProgramme(): void
    {
        [$hall,$values]=$this->createProgramme();$values['event_setup']['rowCategories']['z999_41']='vvip';
        $this->client->request('POST','/admin/evenements/creer',$values);self::assertResponseIsSuccessful();
        self::assertSame(0,$this->entityManager->getRepository(Evenement::class)->count([]));
        $this->client->loginUser($this->createUser('client-workspace@example.com'));
        $this->client->request('POST','/admin/evenements/creer',$values);self::assertResponseStatusCodeSame(403);
    }

    public function testLoadingAnotherHallUsesItsRowsWithoutCreatingAnEvent(): void
    {
        [$first,$values]=$this->createProgramme(1);
        $second=$this->createSalle('Autre salle',['Balcon'=>['Z'=>3]]);
        $values['event_setup']['hall']=(string)$second->getId();$values['load_hall']='1';
        $this->client->request('POST','/admin/evenements/creer',$values);
        self::assertResponseIsSuccessful();self::assertSelectorCount(1,'[data-category-row]');
        self::assertSelectorTextContains('.setup-row','Balcon');
        self::assertSame(0,$this->entityManager->getRepository(Evenement::class)->count([]));
    }

    public function testSeatCategoryPricesAreEnforcedUnderTheSaleLock(): void
    {
        [$event,$shows]=$this->savedProgramme();$show=$shows[0];
        $seats=$this->entityManager->getRepository(InventairePlace::class)->findBy(['representation'=>$show],['id'=>'ASC']);
        $tariffs=$show->getTarifs()->toArray();$standard=current(array_filter($tariffs,fn($t)=>$t->getSeatCategory()->getCode()==='standard'));
        $vip=current(array_filter($tariffs,fn($t)=>$t->getSeatCategory()->getCode()==='vip'));
        $vipSeat=current(array_filter($seats,fn($i)=>$i->getSeatCategory()->getCode()==='vip'));
        $buyer=$this->createUser('buyer-tier@example.com');$sales=static::getContainer()->get(TicketingService::class);
        try {
            $sales->reserve($show->getId(),[['inventaire'=>$vipSeat->getId(),'tarif'=>$standard->getId()]],$buyer->getEmail(),CanalCommande::EnLigne,$buyer);
            self::fail('Un tarif Standard ne doit pas acheter une place VIP.');
        } catch(SaleConflict $error) { self::assertStringContainsString('catégorie',$error->getMessage()); }
        self::assertSame(0,$this->entityManager->getRepository(Commande::class)->count([]));
        $buyer=$this->entityManager->find(\App\Entity\User::class,$buyer->getId());
        $vip=$this->entityManager->find(Tarif::class,$vip->getId());
        $order=$sales->reserve($show->getId(),[['inventaire'=>$vipSeat->getId(),'tarif'=>$vip->getId()]],$buyer->getEmail(),CanalCommande::EnLigne,$buyer);
        $sales->confirm($order->getId());self::assertSame(20000,$order->getBillets()->first()->getPrixPayeCentimes());
        $vip->changerPrix(25000);$this->entityManager->flush();self::assertSame(20000,$order->getBillets()->first()->getPrixPayeCentimes());
        $other=$this->entityManager->getRepository(InventairePlace::class)->findOneBy(['representation'=>$shows[1],'place'=>$vipSeat->getPlace()]);
        self::assertSame('disponible',$other->getStatut()->value);
        $this->client->request('GET','/admin/representations/'.$show->getId().'/ventes');self::assertResponseIsSuccessful();self::assertStringContainsString($order->getReference(),$this->client->getResponse()->getContent());
        $this->client->request('GET','/admin/representations/'.$shows[1]->getId().'/ventes');self::assertResponseIsSuccessful();self::assertStringNotContainsString($order->getReference(),$this->client->getResponse()->getContent());
    }

    public function testCheckoutOnlyOffersTheSelectedSeatsCategoryAndRejectsTampering(): void
    {
        [$event,$shows]=$this->savedProgramme();$show=$shows[0];
        $category=$this->entityManager->getRepository(SeatCategory::class)->findOneBy(['representation'=>$show,'code'=>'vvip']);
        $seat=$this->entityManager->getRepository(InventairePlace::class)->findOneBy(['representation'=>$show,'seatCategory'=>$category]);
        $standardCategory=$this->entityManager->getRepository(SeatCategory::class)->findOneBy(['representation'=>$show,'code'=>'standard']);
        $standard=$this->entityManager->getRepository(Tarif::class)->findOneBy(['seatCategory'=>$standardCategory]);
        $this->client->loginUser($this->createUser('checkout-tier@example.com'));
        $url='/achat/'.$show->getId().'?seats[]='.$seat->getId();
        $crawler=$this->client->request('GET',$url);self::assertResponseIsSuccessful();
        self::assertSelectorCount(1,'[data-line-tariff] option');self::assertSelectorTextContains('[data-line-tariff]','VVIP');
        $values=$crawler->selectButton('Réserver ces places')->form()->getPhpValues();$values['form']['tariff_'.$seat->getId()]=(string)$standard->getId();
        $this->client->request('POST',$url,$values);self::assertResponseIsSuccessful();
        self::assertSame(0,$this->entityManager->getRepository(Commande::class)->count([]));
    }
}
