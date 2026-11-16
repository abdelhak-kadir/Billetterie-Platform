<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\{Evenement, Salle};
use App\Service\DemoProgram;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:demo:shows', description: 'Compléter le jeu reconnu avec Horizon Live et ses spectacles, sans purge ni modification des ventes.')]
final class DemoShowsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DemoProgram $program,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = $this->em->getConnection();
        $database = (string) $db->fetchOne('SELECT DATABASE()');
        $output->writeln('Base des spectacles fictifs : '.$database);
        if ($this->environment !== 'dev' || $database === '' || preg_match('/(?:^test$|_test(?:\d+)?$)/i', $database)) {
            $output->writeln('<error>Complément de démonstration réservé à dev, hors des bases PHPUnit.</error>');
            return Command::FAILURE;
        }
        if ($this->em->getRepository(Evenement::class)->findOneBy(['demoKey' => DemoProgram::CONCERT_KEY]) !== null) {
            $output->writeln('Programme de spectacles déjà présent : conservé sans modification.');
            return Command::SUCCESS;
        }
        $hallId = $db->fetchOne("SELECT r.salle_id FROM billet b JOIN commande c ON c.id=b.commande_id JOIN inventaire_place i ON i.id=b.inventaire_place_id JOIN representation r ON r.id=i.representation_id WHERE c.reference='DEMO-CMD-R1' ORDER BY b.id LIMIT 1");
        $demoOrders = (int) $db->fetchOne("SELECT COUNT(*) FROM commande WHERE reference IN ('DEMO-CMD-R1','DEMO-CMD-R2','DEMO-CMD-R3','DEMO-CMD-R4')");
        $hall = $hallId !== false ? $this->em->find(Salle::class, (int) $hallId) : null;
        if ($demoOrders !== 4 || $hall === null || $hall->getCapacite() !== 400) {
            $output->writeln('<error>Ancienne démonstration non reconnue : aucun événement ajouté ni modifié.</error>');
            return Command::FAILURE;
        }
        $event = $this->program->create('concert', $hall);
        $output->writeln('Programme ajouté : '.$event->getTitre().' — Première partie et Concert principal, le même soir.');
        $output->writeln('Anciennes commandes, billets, comptes, horaires et tarifs conservés. Aucune purge.');

        return Command::SUCCESS;
    }
}
