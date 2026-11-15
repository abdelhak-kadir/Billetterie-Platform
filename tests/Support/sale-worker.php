<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Kernel('test', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
if (!in_array($em->getConnection()->fetchOne('SELECT DATABASE()'), ['billetterie_test', 'billetterie_s2_test'], true)) {
    throw new RuntimeException('Le worker refuse une base non dédiée aux tests.');
}
$service = new App\Service\TicketingService($em, new App\Tests\Support\FrozenClock(new DateTimeImmutable($argv[3])));
$data = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
echo "READY\n"; fflush(STDOUT);
try {
    $result = match ($argv[1]) {
        'reserve' => $service->reserve($data['representation'], [['inventaire' => $data['inventaire'], 'tarif' => $data['tarif']]], 'worker@example.com', App\Enum\CanalCommande::EnLigne)->getId(),
        'confirm' => $service->confirm($data['commande'])->getId(),
        'expire' => $service->expire($data['commande']),
    };
    echo 'RESULT '.json_encode(['ok' => true, 'result' => $result])."\n";
} catch (App\Exception\SaleConflict $error) {
    echo 'RESULT '.json_encode(['ok' => false, 'message' => $error->getMessage()])."\n";
}
