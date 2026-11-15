<?php

declare(strict_types=1);
namespace App\Tests\Integration;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
final class SheetsCommandTest extends DatabaseTestCase
{
    public function testRealExportCommandRefusesThePhpunitDatabase(): void
    {
        $app=new Application(static::$kernel);
        $runner=new CommandTester($app->find('app:sheets:sync'));
        self::assertSame(1,$runner->execute(['--enqueue-existing'=>true]));
        self::assertStringContainsString('interdit sur une base PHPUnit',$runner->getDisplay());
    }
}
