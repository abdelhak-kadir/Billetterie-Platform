<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use App\Service\DemoConsoleRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:demo:bootstrap', description: 'Séquencer migrations et préparation de la démo sous verrou MySQL, uniquement en dev.')]
final class BootstrapDemoCommand extends Command
{
    public function __construct(
        private readonly Connection $db,
        private readonly DemoConsoleRunner $runner,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->environment !== 'dev') {
            $output->writeln('Horizon : migrations et fixtures automatiques ignorées hors de dev.');

            return Command::SUCCESS;
        }

        $database = (string) $this->db->fetchOne('SELECT DATABASE()');
        $output->writeln('Base Symfony effective avant initialisation : '.$database);
        if ($database === '' || preg_match('/(?:^test$|_test(?:\d+)?$)/i', $database)) {
            $output->writeln('<error>Démarrage de démonstration refusé sur une base vide de nom ou réservée à PHPUnit. Désactivez HORIZON_AUTO_DEMO pour les tests.</error>');

            return Command::FAILURE;
        }

        // Verrou de connexion, conservé pendant les DDL et partagé entre les clones.
        $lock = 'horizon-demo-'.substr(hash('sha256', $database), 0, 48);
        $output->writeln('Attente du verrou MySQL de préparation de la démonstration…');
        if ((int) $this->db->fetchOne('SELECT GET_LOCK(?, 120)', [$lock]) !== 1) {
            $output->writeln('<error>Verrou indisponible après 120 secondes. Une autre initialisation est peut-être encore active ; réessayez sans supprimer le volume.</error>');

            return Command::FAILURE;
        }

        try {
            if ($this->db->fetchOne('SELECT DATABASE()') !== $database) {
                throw new \RuntimeException('La base Symfony a changé avant les migrations.');
            }
            foreach (['doctrine:migrations:migrate', 'app:demo:prepare'] as $name) {
                $arguments = $name === 'app:demo:prepare' ? ['--initialize-empty'] : [];
                if ($this->runner->run($name, $arguments, $output) !== Command::SUCCESS) {
                    $output->writeln('<error>Initialisation interrompue pendant '.$name.'. Le serveur HTTP ne sera pas démarré.</error>');

                    return Command::FAILURE;
                }
            }

            return Command::SUCCESS;
        } finally {
            $this->db->fetchOne('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }
}
