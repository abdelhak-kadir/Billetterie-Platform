<?php
/** Conserve les identifiants locaux en mémoire ; seule la base dédiée est changée. */
declare(strict_types=1);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test'; putenv('APP_ENV=test');
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$url = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
if (!preg_match('~^mysql://.+/[^/?]+(?:\?.*)?$~', $url)) { throw new RuntimeException('Une URL MySQL explicite est requise.'); }
$url = preg_replace('~/[^/?]+(\?.*)?$~', '/billetterie_s105_verify$1', $url);
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $url; putenv('DATABASE_URL='.$url);
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test'; putenv('APP_ENV=test');
// Ces deux valeurs sont désormais des surcharges explicites, pas des valeurs à recharger de .env.
$loaded = array_diff(explode(',', $_SERVER['SYMFONY_DOTENV_VARS'] ?? ''), ['DATABASE_URL', 'APP_ENV']);
$_SERVER['SYMFONY_DOTENV_VARS'] = $_ENV['SYMFONY_DOTENV_VARS'] = implode(',', $loaded);
$mode = $argv[1] ?? '';
$kernel = new App\Kernel('test', true);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
if ($db->fetchOne('SELECT DATABASE()') !== 'billetterie_s105_verify_test') { throw new RuntimeException('Base fixtures inattendue.'); }
echo "Base fixtures confirmée : billetterie_s105_verify_test\n";
$kernel->shutdown();
if ($mode === 'phpunit') {
    $argv = [dirname(__DIR__, 2).'/bin/phpunit', '--testsuite', 'Demo Fixtures'];
    $_SERVER['argv'] = $argv; $_SERVER['argc'] = count($argv);
    exit((new PHPUnit\TextUI\Application())->run($argv));
}
$application = new Symfony\Bundle\FrameworkBundle\Console\Application(new App\Kernel('test', true));
$application->run(new Symfony\Component\Console\Input\ArgvInput(array_merge(['bin/console'], array_slice($argv, 2))));
