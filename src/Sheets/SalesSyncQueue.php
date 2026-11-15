<?php

declare(strict_types=1);
namespace App\Sheets;
use App\Clock\Clock;
use App\Entity\Billet;
use Doctrine\DBAL\Connection;

final class SalesSyncQueue
{
    public function __construct(private readonly Connection $db, private readonly Clock $clock) {}

    /** @param iterable<Billet> $tickets Appelé dans la transaction de vente, sans réseau. */
    public function enqueue(iterable $tickets): int
    {
        if (!$this->db->isTransactionActive()) { throw new \LogicException('La file doit participer à la transaction métier.'); }
        $count = 0; $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        foreach ($tickets as $ticket) {
            if ($ticket->getCommande()->getPayeA() === null) { continue; }
            $payload = json_encode(SaleProjection::fromTicket($ticket), JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $this->db->executeStatement(<<<'SQL'
INSERT INTO sheets_sync_job (ticket_reference,payload,revision,status,attempts,next_attempt_at,last_error,updated_at,synced_at,synced_revision,synced_target)
VALUES (:reference,:payload,1,'pending',0,:now,NULL,:now,NULL,0,NULL) AS incoming
ON DUPLICATE KEY UPDATE
revision=sheets_sync_job.revision+IF(sheets_sync_job.payload<>incoming.payload,1,0),
status=IF(sheets_sync_job.payload<>incoming.payload,'pending',sheets_sync_job.status),
attempts=IF(sheets_sync_job.payload<>incoming.payload,0,sheets_sync_job.attempts),
next_attempt_at=IF(sheets_sync_job.payload<>incoming.payload,incoming.next_attempt_at,sheets_sync_job.next_attempt_at),
last_error=IF(sheets_sync_job.payload<>incoming.payload,NULL,sheets_sync_job.last_error),
updated_at=IF(sheets_sync_job.payload<>incoming.payload,incoming.updated_at,sheets_sync_job.updated_at),
payload=incoming.payload
SQL, ['reference'=>$ticket->getReference(),'payload'=>$payload,'now'=>$now]);
            ++$count;
        }
        return $count;
    }
}
