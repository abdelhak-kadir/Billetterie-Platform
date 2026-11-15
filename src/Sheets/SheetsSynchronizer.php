<?php

declare(strict_types=1);
namespace App\Sheets;
use App\Clock\Clock;
use Doctrine\DBAL\Connection;

final class SheetsSynchronizer
{
    public function __construct(private readonly Connection $db, private readonly SheetsGateway $gateway, private readonly Clock $clock, private readonly bool $enabled) {}
    public function enabled(): bool { return $this->enabled; }

    public function summary(): array
    {
        $counts = $this->db->fetchAssociative(<<<'SQL'
SELECT COALESCE(SUM(CASE WHEN status='synced' AND synced_revision=revision AND synced_target=:target THEN 1 ELSE 0 END),0) AS synced,
COALESCE(SUM(CASE WHEN NOT(status='synced' AND synced_revision=revision AND COALESCE(synced_target,'')=:target) AND last_error IS NULL THEN 1 ELSE 0 END),0) AS pending,
COALESCE(SUM(CASE WHEN NOT(status='synced' AND synced_revision=revision AND COALESCE(synced_target,'')=:target) AND last_error IS NOT NULL THEN 1 ELSE 0 END),0) AS failed
FROM sheets_sync_job
SQL, ['target'=>$this->gateway->target()]);
        $lastError = $this->db->fetchOne("SELECT last_error FROM sheets_sync_job WHERE last_error IS NOT NULL ORDER BY updated_at DESC,id DESC LIMIT 1") ?: null;
        return ['enabled'=>$this->enabled,'lastError'=>$lastError]+array_map('intval',$counts);
    }

    public function retryFailed(): int
    {
        return $this->db->executeStatement("UPDATE sheets_sync_job SET next_attempt_at=? WHERE last_error IS NOT NULL", [$this->now()->format('Y-m-d H:i:s')]);
    }

    /** La vente n'attend jamais ce worker. Un seul worker par base et destination. */
    public function run(int $limit = 100): array
    {
        $result = ['sent'=>0,'failed'=>0,'busy'=>false];
        if (!$this->enabled) { return $result; }
        if ($limit < 1 || $limit > 1000) { throw new \InvalidArgumentException('Limite de lot entre 1 et 1000.'); }
        $lock = 'horizon_sheets_'.substr(hash('sha256', $this->db->getDatabase().'|'.$this->gateway->target()),0,40);
        if ((int)$this->db->fetchOne('SELECT GET_LOCK(?,0)',[$lock]) !== 1) { $result['busy']=true; return $result; }
        try {
            $now = $this->now()->format('Y-m-d H:i:s'); $target = $this->gateway->target();
            $jobs = $this->db->fetchAllAssociative("SELECT * FROM sheets_sync_job WHERE next_attempt_at<=:now AND (status<>'synced' OR synced_revision<>revision OR COALESCE(synced_target,'')<>:target) ORDER BY id LIMIT ".$limit, ['now'=>$now,'target'=>$target]);
            foreach ($jobs as $job) {
                try {
                    $payload = json_decode($job['payload'],true,512,JSON_THROW_ON_ERROR);
                    $values = array_map(static fn($key) => $payload[$key], SaleProjection::COLUMNS);
                    $this->gateway->write((int)$job['id']+1,$values);
                    $this->db->executeStatement("UPDATE sheets_sync_job SET status='synced',synced_revision=:revision,synced_target=:target,synced_at=:now,last_error=NULL WHERE id=:id AND revision=:revision", ['revision'=>$job['revision'],'target'=>$target,'now'=>$now,'id'=>$job['id']]);
                    ++$result['sent'];
                } catch (\Throwable $error) {
                    $delay = min(3600,5*(2**min((int)$job['attempts'],10)));
                    // Messages de l'adaptateur contrôlés ; les erreurs inconnues ne publient pas leur contenu.
                    $message = $error instanceof SyncFailure
                        ? $error->getMessage() : 'Erreur de synchronisation ; consulter la configuration et relancer.';
                    $this->db->executeStatement("UPDATE sheets_sync_job SET status='failed',attempts=attempts+1,next_attempt_at=:next,last_error=:error WHERE id=:id AND revision=:revision", [
                        'next'=>$this->now()->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s'),
                        'error'=>mb_substr($message,0,255),'id'=>$job['id'],'revision'=>$job['revision'],
                    ]);
                    ++$result['failed'];
                    break; // Évite une rafale d'appels quand le service est indisponible.
                }
            }
        } finally { $this->db->fetchOne('SELECT RELEASE_LOCK(?)',[$lock]); }
        return $result;
    }

    private function now(): \DateTimeImmutable { return $this->clock->now()->setTimezone(new \DateTimeZone('UTC')); }
}
