<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Chaque commande dispose d'un état Doctrine neuf, notamment après les DDL MySQL. */
class DemoConsoleRunner
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) {}

    public function run(string $command, array $arguments, OutputInterface $output): int
    {
        $process = proc_open(
            [PHP_BINARY, 'bin/console', $command, '--env=dev', '--no-interaction', ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            $this->projectDir,
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Impossible de lancer la commande Symfony '.$command.'.');
        }
        while (($line = fgets($pipes[1])) !== false) {
            $output->write($line);
        }
        fclose($pipes[1]);

        return proc_close($process);
    }
}
