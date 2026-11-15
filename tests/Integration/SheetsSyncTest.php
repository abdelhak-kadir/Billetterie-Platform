<?php

declare(strict_types=1);
namespace App\Tests\Integration;
use App\Exception\SaleConflict;
use App\Service\TicketingService;
use App\Sheets\{SaleProjection,SalesSyncQueue,SheetsSynchronizer};
use App\Tests\Support\RecordingSheetsGateway;
use Doctrine\DBAL\DriverManager;

final class SheetsSyncTest extends TicketingTestCase
{
    private SalesSyncQueue $queue;
    private RecordingSheetsGateway $gateway;
    private SheetsSynchronizer $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $db=$this->entityManager->getConnection();
        $this->queue=new SalesSyncQueue($db,$this->clock);
        $this->sales=new TicketingService($this->entityManager,$this->clock,600,$this->queue);
        $this->gateway=new RecordingSheetsGateway();
        $this->sync=new SheetsSynchronizer($db,$this->gateway,$this->clock,true);
    }

    public function testConfirmedSaleHasOneSafeProjectionAndReplayIsIdempotent(): void
    {
        $order=$this->reserve(); $this->sales->confirm($order->getId());
        $before=$this->job(); $payload=json_decode($before['payload'],true);
        self::assertEqualsCanonicalizing(SaleProjection::COLUMNS,array_keys($payload));
        self::assertSame(10000,$payload['prix_paye_centimes']);
        self::assertStringNotContainsString('@',$before['payload']);
        self::assertStringNotContainsString('owner',$before['payload']);
        $this->sales->confirm($order->getId());
        self::assertSame(1,(int)$this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM sheets_sync_job'));
        self::assertSame($before['revision'],$this->job()['revision']);
        self::assertSame(1,$this->sync->run()['sent']);
        $this->entityManager->getConnection()->transactional(fn()=>$this->queue->enqueue($order->getBillets()));
        self::assertSame('synced',$this->job()['status']);
        self::assertSame(0,$this->sync->run()['sent']);
    }

    public function testTimeoutAfterWriteRetriesTheSameRowAndKeepsSalePaid(): void
    {
        $order=$this->reserve();$this->sales->confirm($order->getId());
        $this->gateway->failAfterWrite=true;
        self::assertSame(1,$this->sync->run()['failed']);
        self::assertSame('failed',$this->job()['status']);
        self::assertSame(1,(int)$this->job()['attempts']);
        self::assertSame('payee',$this->entityManager->getConnection()->fetchOne('SELECT statut FROM commande WHERE id=?',[$order->getId()]));
        self::assertSame(0,$this->sync->run()['sent']);
        self::assertCount(1,$this->gateway->writes);
        $this->clock->advance('+5 seconds');$this->gateway->failAfterWrite=false;
        self::assertSame(1,$this->sync->run()['sent']);
        self::assertCount(1,$this->gateway->rows);
        self::assertSame($this->gateway->writes[0],$this->gateway->writes[1]);
        self::assertSame('synced',$this->job()['status']);
    }

    public function testRefundUpdatesOriginalRowAtHistoricalPrice(): void
    {
        $order=$this->reserve();$this->sales->confirm($order->getId());$this->sync->run();
        $this->sales->cancel($order->getId());
        self::assertSame(2,(int)$this->job()['revision']);
        self::assertSame(1,$this->sync->run()['sent']);
        self::assertCount(1,$this->gateway->rows);
        $row=array_combine(SaleProjection::COLUMNS,array_values($this->gateway->rows)[0]);
        self::assertSame(10000,$row['prix_paye_centimes']);
        self::assertSame(10000,$row['remboursement_centimes']);
        self::assertSame(0,$row['net_centimes']);
        self::assertSame('annule',$row['statut_billet']);
    }

    public function testNewRefundRevisionDuringNetworkWriteRemainsPending(): void
    {
        $order=$this->reserve();$this->sales->confirm($order->getId());
        $this->gateway->duringWrite=fn()=>$this->sales->cancel($order->getId());
        $this->sync->run();
        self::assertSame('pending',$this->job()['status']);
        self::assertSame(2,(int)$this->job()['revision']);
        self::assertSame(0,$this->sync->summary()['synced']);
        self::assertSame(1,$this->sync->run()['sent']);
        self::assertSame(2,(int)$this->job()['synced_revision']);
        self::assertCount(1,$this->gateway->rows);
    }

    public function testLateSqlFailureRollsBackNewTicketAndExportTogether(): void
    {
        $first=$this->reserve();$this->sales->confirm($first->getId());
        $db=$this->entityManager->getConnection();
        // Injection d'une incohérence de stock : l'index du billet valide doit rester le dernier garde.
        $db->executeStatement("UPDATE inventaire_place SET statut='disponible' WHERE id=?",[$this->inventories[0]]);
        $second=$this->reserve();
        try { $this->sales->confirm($second->getId()); self::fail('Le doublon valide doit être refusé.'); }
        catch (SaleConflict) {}
        self::assertSame(1,(int)$db->fetchOne('SELECT COUNT(*) FROM sheets_sync_job'));
        self::assertSame(1,(int)$db->fetchOne('SELECT COUNT(*) FROM billet'));
        self::assertSame('en_attente',$db->fetchOne('SELECT statut FROM commande WHERE id=?',[$second->getId()]));
    }

    public function testDisabledWorkerPreservesPendingJobsWithoutNetwork(): void
    {
        $order=$this->reserve();$this->sales->confirm($order->getId());
        $off=new SheetsSynchronizer($this->entityManager->getConnection(),$this->gateway,$this->clock,false);
        self::assertSame(0,$off->run()['sent']);self::assertCount(0,$this->gateway->writes);
        self::assertSame(1,$off->summary()['pending']);self::assertFalse($off->summary()['enabled']);
    }

    public function testFailedBackfillKeepsRetryStateAndManualRetryCanResume(): void
    {
        $order=$this->reserve();$this->sales->confirm($order->getId());
        $this->gateway->failAfterWrite=true;$this->sync->run();
        $this->entityManager->getConnection()->transactional(fn()=>$this->queue->enqueue($order->getBillets()));
        self::assertSame(1,(int)$this->job()['attempts']);self::assertSame('failed',$this->job()['status']);
        $this->gateway->failAfterWrite=false;$this->sync->retryFailed();
        self::assertSame(1,$this->sync->run()['sent']);
    }

    public function testAnotherConnectionCannotRunASecondWorkerAtTheSameTime(): void
    {
        $order=$this->reserve();$this->sales->confirm($order->getId());
        $db=DriverManager::getConnection($this->entityManager->getConnection()->getParams());
        try {
            $other=new SheetsSynchronizer($db,$this->gateway,$this->clock,true);
            $this->gateway->duringWrite=function()use($other):void {self::assertTrue($other->run()['busy']);};
            self::assertSame(1,$this->sync->run()['sent']);self::assertCount(1,$this->gateway->writes);
        } finally {$db->close();}
    }

    public function testChangingDestinationResendsSyncedJobs(): void
    {
        $order=$this->reserve();$this->sales->confirm($order->getId());$this->sync->run();
        $this->gateway->destination='second-test-spreadsheet';
        self::assertSame(1,$this->sync->summary()['pending']);
        self::assertSame(1,$this->sync->run()['sent']);
        self::assertSame($this->gateway->target(),$this->job()['synced_target']);
    }

    private function job(): array { return $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM sheets_sync_job ORDER BY id LIMIT 1'); }
}
