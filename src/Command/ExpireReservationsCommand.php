<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TicketingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:reservations:expire', description: 'Libère les réservations expirées, sans toucher aux ventes confirmées.')]
final class ExpireReservationsCommand extends Command
{
    public function __construct(private readonly TicketingService $ticketing) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%d commande(s) expirée(s).', $this->ticketing->expireDue()));
        return Command::SUCCESS;
    }
}
