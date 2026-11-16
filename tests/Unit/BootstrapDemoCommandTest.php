<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\BootstrapDemoCommand;
use App\Service\DemoConsoleRunner;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class BootstrapDemoCommandTest extends TestCase
{
    #[DataProvider('nonDemoEnvironments')]
    public function testOtherEnvironmentsNeverMigrateOrLoadFixtures(string $environment): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('fetchOne');
        $runner = $this->createMock(DemoConsoleRunner::class);
        $runner->expects(self::never())->method('run');
        $tester = new CommandTester(new BootstrapDemoCommand($db, $runner, $environment));
        self::assertSame(0, $tester->execute([]));
    }

    public static function nonDemoEnvironments(): array
    {
        return [['prod'], ['test']];
    }

    #[DataProvider('phpunitDatabaseNames')]
    public function testPhpunitDatabaseIsRejectedBeforeAnyLockOrMigration(string $database): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::once())->method('fetchOne')->with('SELECT DATABASE()')->willReturn($database);
        $runner = $this->createMock(DemoConsoleRunner::class);
        $runner->expects(self::never())->method('run');
        $tester = new CommandTester(new BootstrapDemoCommand($db, $runner, 'dev'));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('PHPUnit', $tester->getDisplay());
    }

    public static function phpunitDatabaseNames(): array
    {
        return [['test'], ['billetterie_test'], ['billetterie_s105_verify_test'], ['billetterie_test2']];
    }

    public function testContendedLockDoesNotRunMigrationOrReleaseAnotherOwnerLock(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::exactly(2))->method('fetchOne')->willReturnCallback(static function (string $sql): mixed {
            return $sql === 'SELECT DATABASE()' ? 'demo' : 0;
        });
        $runner = $this->createMock(DemoConsoleRunner::class);
        $runner->expects(self::never())->method('run');
        $tester = new CommandTester(new BootstrapDemoCommand($db, $runner, 'dev'));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Verrou indisponible', $tester->getDisplay());
    }

    public function testMigrationsPrecedeExistingPreparationAndReleaseLock(): void
    {
        $calls = [];
        $tester = $this->runner($calls, 0);
        self::assertSame(0, $tester->execute([]));
        self::assertSame(['locked', 'migration', 'prepare', 'released'], $calls);
    }

    public function testMigrationFailureNeverLoadsFixturesAndReleasesLock(): void
    {
        $calls = [];
        $tester = $this->runner($calls, 1);
        self::assertSame(1, $tester->execute([]));
        self::assertSame(['locked', 'migration', 'released'], $calls);
    }

    public function testUnexpectedFailureStillReleasesLock(): void
    {
        $calls = [];
        $tester = $this->runner($calls, new \RuntimeException('migration failure'));
        try {
            $tester->execute([]);
            self::fail('The migration exception must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('migration failure', $exception->getMessage());
        }
        self::assertSame(['locked', 'migration', 'released'], $calls);
    }

    private function runner(array &$calls, int|\Throwable $migrationResult): CommandTester
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchOne')->willReturnCallback(static function (string $sql, array $params = []) use (&$calls): mixed {
            if ($sql === 'SELECT DATABASE()') { return 'demo'; }
            self::assertLessThanOrEqual(64, strlen($params[0]));
            $calls[] = str_contains($sql, 'GET_LOCK') ? 'locked' : 'released';

            return 1;
        });
        $runner = $this->createMock(DemoConsoleRunner::class);
        $runner->method('run')->willReturnCallback(static function (string $name, array $arguments) use (&$calls, $migrationResult): int {
            if ($name === 'doctrine:migrations:migrate') {
                self::assertSame([], $arguments);
                $calls[] = 'migration';
                if ($migrationResult instanceof \Throwable) { throw $migrationResult; }

                return $migrationResult;
            }
            self::assertSame('app:demo:prepare', $name);
            self::assertSame(['--initialize-empty'], $arguments);
            $calls[] = 'prepare';

            return 0;
        });

        return new CommandTester(new BootstrapDemoCommand($db, $runner, 'dev'));
    }
}
