<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{ArrayInput, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:demo:prepare', description: 'Initialiser une base de démonstration vide, ou conserver son jeu existant.')]
final class PrepareDemoCommand extends Command
{
    public function __construct(private readonly Connection $db, #[Autowire('%kernel.environment%')] private readonly string $environment)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('initialize-empty', null, InputOption::VALUE_NONE, 'Autoriser les fixtures uniquement si toutes les tables métier sont vides.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $database = (string) $this->db->fetchOne('SELECT DATABASE()');
        $output->writeln('Base de démonstration effective : '.$database);
        if ($this->environment !== 'dev' || preg_match('/(?:^test$|_test(?:\d+)?$)/i', $database)) {
            $output->writeln('<error>Initialisation réservée à dev, hors des bases PHPUnit.</error>');
            return Command::FAILURE;
        }

        $populated = false;
        foreach ($this->db->createSchemaManager()->listTableNames() as $table) {
            if (in_array($table, ['app_user', 'doctrine_migration_versions'], true)) { continue; }
            if ((int) $this->db->fetchOne('SELECT COUNT(*) FROM '.$this->db->quoteIdentifier($table)) > 0) { $populated = true; }
        }
        if ($populated) {
            // Une base reconnue n'est jamais purgée, même après des achats ou ajouts.
            $demoOrders = (int) $this->db->fetchOne("SELECT COUNT(*) FROM commande WHERE reference IN ('DEMO-CMD-R1','DEMO-CMD-R2','DEMO-CMD-R3','DEMO-CMD-R4')");
            if ($demoOrders !== 4) {
                $output->writeln('<error>Base non vide non reconnue : aucune fixture chargée. Utilisez un autre projet Compose et un volume neuf.</error>');
                return Command::FAILURE;
            }
            $output->writeln('Démonstration existante conservée : aucune purge.');
        } else {
            $unknownUsers = (int) $this->db->fetchOne("SELECT COUNT(*) FROM app_user WHERE email NOT IN ('client@example.com','guichet@example.com','admin@example.com')");
            if (!$input->getOption('initialize-empty') || $unknownUsers !== 0) {
                $output->writeln('<error>Chargement refusé : autorisation --initialize-empty requise et seuls les comptes fictifs sont admis sur une base à initialiser.</error>');
                return Command::FAILURE;
            }
            // Même connexion et même environnement pour le garde et la commande existante.
            if ($this->db->fetchOne('SELECT DATABASE()') !== $database) { throw new \RuntimeException('La connexion a changé.'); }
            $output->writeln('Base métier vide, dédiée à cette installation de démonstration ; app_user est exclue de la purge.');
            $args = new ArrayInput(['--purge-exclusions' => ['app_user']]);
            $args->setInteractive(false);
            if ($this->getApplication()->find('doctrine:fixtures:load')->run($args, $output) !== Command::SUCCESS) { return Command::FAILURE; }
        }
        $args = new ArrayInput([]); $args->setInteractive(false);
        return $this->getApplication()->find('app:demo:users')->run($args, $output);
    }
}
