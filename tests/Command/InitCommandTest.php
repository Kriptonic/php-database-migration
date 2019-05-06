<?php

namespace Migrate\Command;

use Migrate\Manager;
use Migrate\Test\Command\AbstractCommandTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class InitCommandTest
 *
 * @package Migrate\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class InitCommandTest extends AbstractCommandTester
{
    public function setUp()
    {
        $this->cleanEnvironment();
        $this->createEnvironmentAndDatabaseConfiguration();
    }

    public function tearDown()
    {
        $this->cleanEnvironment();
    }

    public function testExecute()
    {
        $application = new Manager(self::$testManagerConfig);
        $application->add(new InitCommand());

        $command = $application->find('migrate:init');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $expected = "connected\nchangelog table (changelog) successfully created\n";

        $this->assertEquals($expected, $commandTester->getDisplay());

    }
}
