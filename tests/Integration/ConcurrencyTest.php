<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Billet, Commande, InventairePlace};
use App\Enum\{StatutCommande, StatutInventairePlace};
use Doctrine\DBAL\DriverManager;

final class ConcurrencyTest extends TicketingTestCase
{
    public function testTwoProcessesActuallyOverlapAndOnlyOneReservesA1(): void
    {
        $data = ['representation' => $this->r1, 'inventaire' => $this->inventories[0], 'tarif' => $this->tariff];
        $results = $this->race([['reserve', $data], ['reserve', $data]], '2030-01-01T12:00:00Z');
        self::assertSame(1, count(array_filter($results, static fn ($r) => $r['ok'])));
        self::assertSame(1, $this->entityManager->getRepository(Commande::class)->count([]));
        $this->entityManager->clear();
        $inventory = $this->entityManager->find(InventairePlace::class, $this->inventories[0]);
        self::assertSame(StatutInventairePlace::Reservee, $inventory->getStatut());
        self::assertNotNull($inventory->getCommandeReservation());
        $this->sales->confirm($inventory->getCommandeReservation()->getId());
        self::assertSame(1, $this->entityManager->getRepository(Billet::class)->count([]));
        self::assertSame(3, $this->entityManager->getRepository(InventairePlace::class)->count(['representation' => $this->r2, 'statut' => StatutInventairePlace::Disponible]));
    }

    public function testExpirationAndConfirmationOverlapAtDeadlineWithoutIssuingTicket(): void
    {
        $id = $this->reserve([0, 1])->getId();
        $results = $this->race([['confirm', ['commande' => $id]], ['expire', ['commande' => $id]]], '2030-01-01T12:10:00Z');
        self::assertFalse($results[0]['ok']);
        self::assertTrue($results[1]['ok']);
        $this->entityManager->clear();
        self::assertSame(StatutCommande::Expiree, $this->entityManager->find(Commande::class, $id)->getStatut());
        self::assertSame(0, $this->entityManager->getRepository(Billet::class)->count([]));
        self::assertSame(3, $this->entityManager->getRepository(InventairePlace::class)->count(['representation' => $this->r1, 'statut' => StatutInventairePlace::Disponible]));
    }

    /** Deux sessions sont observées en attente FOR UPDATE avant de libérer la barrière. */
    private function race(array $operations, string $time): array
    {
        $gate = DriverManager::getConnection($this->entityManager->getConnection()->getParams());
        self::assertContains($gate->fetchOne('SELECT DATABASE()'), ['billetterie_test', 'billetterie_s2_test']);
        $workers = [];
        $gate->beginTransaction();
        $gate->fetchOne('SELECT id FROM representation WHERE id=? FOR UPDATE', [$this->r1]);
        try {
            foreach ($operations as [$operation, $data]) {
                $process = proc_open([PHP_BINARY, dirname(__DIR__).'/Support/sale-worker.php', $operation, json_encode($data, JSON_THROW_ON_ERROR), $time], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
                $workers[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2], 'text' => ''];
            }
            $deadline = microtime(true) + 10;
            $blocked = 0;
            do {
                foreach ($workers as &$worker) { $worker['text'] .= stream_get_contents($worker['out']); } unset($worker);
                $blocked = count(array_filter($gate->fetchAllAssociative('SHOW FULL PROCESSLIST'), static fn ($row) =>
                    str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE') && str_contains(strtolower($row['Info'] ?? ''), 'representation'),
                ));
                if ($blocked >= 2) { break; }
                usleep(10000); // Synchronisation inter-processus, pas d'attente d'expiration métier.
            } while (microtime(true) < $deadline);
            self::assertGreaterThanOrEqual(2, $blocked, 'Les deux connexions doivent se chevaucher réellement sous verrou.');
            foreach ($workers as $worker) { self::assertStringNotContainsString('RESULT', $worker['text']); }
            $gate->commit();
            $results = [];
            foreach ($workers as &$worker) {
                $deadline = microtime(true) + 10;
                do {
                    $worker['text'] .= stream_get_contents($worker['out']);
                    if (str_contains($worker['text'], 'RESULT ')) { break; }
                    usleep(10000);
                } while (microtime(true) < $deadline);
                $error = stream_get_contents($worker['err']);
                self::assertMatchesRegularExpression('/RESULT (.+)/', $worker['text'], $error);
                preg_match('/RESULT (.+)/', $worker['text'], $match);
                $results[] = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
            } unset($worker);
            return $results;
        } finally {
            if ($gate->isTransactionActive()) { $gate->rollBack(); }
            foreach ($workers as $worker) {
                $status = proc_get_status($worker['process']);
                if ($status['running']) { proc_terminate($worker['process']); }
                fclose($worker['out']); fclose($worker['err']); proc_close($worker['process']);
            }
            $gate->close();
        }
    }
}
