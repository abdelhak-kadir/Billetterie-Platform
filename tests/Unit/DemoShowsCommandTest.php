<?php

declare(strict_types=1);
namespace App\Tests\Unit;

use App\Command\DemoShowsCommand;
use App\Entity\Evenement;
use App\Enum\TypeEvenement;
use App\Service\DemoProgram;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoShowsCommandTest extends TestCase
{
    #[DataProvider('refusedContexts')]
    public function testForbiddenEnvironmentOrDatabaseNeverInspectsOrCreatesEvents(string $environment,string $database): void
    {
        $db=$this->createMock(Connection::class);$db->expects(self::once())->method('fetchOne')->with('SELECT DATABASE()')->willReturn($database);
        $em=$this->createMock(EntityManagerInterface::class);$em->method('getConnection')->willReturn($db);
        $em->expects(self::never())->method('getRepository');
        $program=$this->createMock(DemoProgram::class);$program->expects(self::never())->method('create');
        $tester=new CommandTester(new DemoShowsCommand($em,$program,$environment));
        self::assertSame(1,$tester->execute([]));
    }
    public static function refusedContexts(): array { return [['prod','demo'],['test','demo_test'],['dev','billetterie_test'],['dev','test'],['dev','']]; }

    public function testExistingVersionIsPreservedEvenIfAdministratorRenamedIt(): void
    {
        $db=$this->createMock(Connection::class);$db->expects(self::once())->method('fetchOne')->willReturn('demo');
        $em=$this->createMock(EntityManagerInterface::class);$em->method('getConnection')->willReturn($db);
        $repo=$this->createMock(EntityRepository::class);
        $repo->expects(self::once())->method('findOneBy')->with(['demoKey'=>DemoProgram::CONCERT_KEY])->willReturn(new Evenement('Programme renommé',TypeEvenement::Concert));
        $em->method('getRepository')->willReturn($repo);
        $program=$this->createMock(DemoProgram::class);$program->expects(self::never())->method('create');
        $tester=new CommandTester(new DemoShowsCommand($em,$program,'dev'));
        self::assertSame(0,$tester->execute([]));
        self::assertStringContainsString('conservé sans modification',$tester->getDisplay());
    }

    public function testUnknownDatabaseNeverCreatesProgram(): void
    {
        $db=$this->createMock(Connection::class);$db->method('fetchOne')->willReturnCallback(static fn(string $sql)=>$sql==='SELECT DATABASE()'?'demo':false);
        $em=$this->createMock(EntityManagerInterface::class);$em->method('getConnection')->willReturn($db);
        $repo=$this->createMock(EntityRepository::class);$repo->method('findOneBy')->willReturn(null);$em->method('getRepository')->willReturn($repo);
        $program=$this->createMock(DemoProgram::class);$program->expects(self::never())->method('create');
        $tester=new CommandTester(new DemoShowsCommand($em,$program,'dev'));
        self::assertSame(1,$tester->execute([]));
        self::assertStringContainsString('non reconnue',$tester->getDisplay());
    }
}
