<?php

declare(strict_types=1);
namespace App\Command;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
#[AsCommand(name: 'app:demo:users', description: 'Créer les trois comptes fictifs de démonstration, sans purge ni modification des comptes existants.')]
final class DemoUsersCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher, #[Autowire('%kernel.environment%')] private string $environment) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($this->environment, ['dev', 'test'], true)) { $output->writeln('Comptes de démonstration interdits dans cet environnement.'); return Command::FAILURE; }
        foreach (['client' => 'ROLE_CLIENT', 'guichet' => 'ROLE_GUICHETIER', 'admin' => 'ROLE_ADMIN'] as $name => $role) {
            $email = $name.'@example.com';
            if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) { $output->writeln($email.' : conservé sans modification.'); continue; }
            $user = new User($email, $role); $user->setPassword($this->hasher->hashPassword($user, 'HorizonDemo2026!'));
            $this->em->persist($user); $this->em->flush(); $output->writeln($email.' : créé (compte fictif de démonstration).');
        }
        return Command::SUCCESS;
    }
}
