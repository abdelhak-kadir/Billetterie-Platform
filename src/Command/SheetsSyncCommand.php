<?php

declare(strict_types=1);
namespace App\Command;
use App\Entity\Billet;
use App\Sheets\{SalesSyncQueue,SheetsSynchronizer};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name:'app:sheets:sync',description:'Exporter automatiquement les ventes et remboursements confirmés, avec reprise sans doublon.')]
final class SheetsSyncCommand extends Command
{
    public function __construct(private readonly SheetsSynchronizer $sync,private readonly SalesSyncQueue $queue,private readonly EntityManagerInterface $em, #[Autowire('%kernel.environment%')] private readonly string $environment) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('watch',null,InputOption::VALUE_NONE,'Surveiller la file toutes les cinq secondes.')
            ->addOption('limit',null,InputOption::VALUE_REQUIRED,'Taille maximale du lot.',100)
            ->addOption('retry-failed',null,InputOption::VALUE_NONE,'Réessayer maintenant les erreurs différées.')
            ->addOption('enqueue-existing',null,InputOption::VALUE_NONE,'Inclure les billets historiques confirmés sans réinitialiser les envois identiques.')
            ->addOption('enabled-check',null,InputOption::VALUE_NONE,'Code 0 si Sheets est activé, sans appel réseau.')
            ->addOption('status',null,InputOption::VALUE_NONE,'Afficher les compteurs sans appel réseau.');
    }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        if ($input->getOption('enabled-check')) { return $this->sync->enabled() ? 0 : 1; }
        if ($input->getOption('status')) { $output->writeln(json_encode($this->sync->summary(),JSON_THROW_ON_ERROR)); return 0; }
        $database=(string)$this->em->getConnection()->fetchOne('SELECT DATABASE()');
        if ($this->environment === 'test' || preg_match('/(?:^test$|_test(?:\d+)?$)/i',$database)) {
            $output->writeln('<error>Worker réel interdit sur une base PHPUnit ; utiliser le client simulé des tests.</error>');
            return 1;
        }
        if ($input->getOption('enqueue-existing')) {
            $tickets=$this->em->createQuery('SELECT b FROM App\\Entity\\Billet b JOIN b.commande c WHERE c.payeA IS NOT NULL')->getResult();
            $this->em->getConnection()->transactional(fn() => $this->queue->enqueue($tickets));
            $output->writeln('Billets historiques examinés : '.count($tickets));
        }
        if (!$this->sync->enabled()) { $output->writeln('Google Sheets désactivé ; les demandes restent conservées en MySQL.'); return 0; }
        if ($input->getOption('retry-failed')) { $this->sync->retryFailed(); }
        do {
            $result=$this->sync->run((int)$input->getOption('limit'));
            $output->writeln(json_encode($result,JSON_THROW_ON_ERROR));
            if (!$input->getOption('watch')) { return $result['failed']>0 ? 1 : 0; }
            sleep(5);
        } while (true);
    }
}
