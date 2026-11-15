<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\PrepareDemoCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PrepareDemoCommandTest extends TestCase
{
    public function testProductionNeverInspectsOrPurgesBusinessData(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::once())->method('fetchOne')->willReturn('billetterie');
        $db->expects(self::never())->method('createSchemaManager');
        $runner = new CommandTester(new PrepareDemoCommand($db, 'prod'));
        self::assertSame(1, $runner->execute(['--initialize-empty'=>true]));
    }

    public function testPhpunitDatabaseCannotBeInitializedEvenInDev(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::once())->method('fetchOne')->willReturn('billetterie_test');
        $db->expects(self::never())->method('createSchemaManager');
        $runner = new CommandTester(new PrepareDemoCommand($db, 'dev'));
        self::assertSame(1, $runner->execute(['--initialize-empty'=>true]));
    }

    public function testUnknownPopulatedDatabaseIsRefused(): void
    {
        $runner = new CommandTester(new PrepareDemoCommand($this->connection(true, 0), 'dev'));
        self::assertSame(1, $runner->execute(['--initialize-empty'=>true]));
        self::assertStringContainsString('aucune fixture chargée', $runner->getDisplay());
    }

    public function testEmptyDatabaseNeedsExplicitInitialization(): void
    {
        $runner = new CommandTester(new PrepareDemoCommand($this->connection(false, 0), 'dev'));
        self::assertSame(1, $runner->execute([]));
        self::assertStringContainsString('--initialize-empty requise', $runner->getDisplay());
    }

    public function testExistingNonDemoAccountsArePreservedByRefusingInitialization(): void
    {
        $runner = new CommandTester(new PrepareDemoCommand($this->connection(false, 1), 'dev'));
        self::assertSame(1, $runner->execute(['--initialize-empty'=>true]));
    }

    private function connection(bool $populated, int $unknownUsers): Connection
    {
        $db = $this->createMock(Connection::class);
        $schema = $this->createMock(AbstractSchemaManager::class);
        $schema->method('listTableNames')->willReturn(['app_user','commande','doctrine_migration_versions']);
        $db->method('createSchemaManager')->willReturn($schema);
        $db->method('quoteIdentifier')->willReturn('`commande`');
        $db->method('fetchOne')->willReturnCallback(static function (string $sql) use ($populated, $unknownUsers): mixed {
            if ($sql === 'SELECT DATABASE()') { return 'billetterie'; }
            if (str_contains($sql, 'NOT IN')) { return $unknownUsers; }
            if (str_contains($sql, 'reference IN')) { return 0; }
            return (int) $populated;
        });
        return $db;
    }
}
