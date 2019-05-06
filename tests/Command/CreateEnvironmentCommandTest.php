<?php

namespace Migrate\Test\Command;

use Migrate\Command\CreateEnvironmentCommand;
use Migrate\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class CreateEnvironmentCommandTest
 *
 * @package Migrate\Test\Command
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
class CreateEnvironmentCommandTest extends AbstractCommandTester
{
    /** @var Manager */
    protected $manager;

    /** @var Command */
    private $command;

    /** @var CommandTester */
    private $commandTester;

    public function setUp()
    {
        $this->cleanEnvironment();

        // All tests in this file require this basic setup.
        $this->manager = new Manager(static::$testManagerConfig);
        $this->manager->add(new CreateEnvironmentCommand());

        $this->command = $this->manager->find('create:env');
        $this->commandTester = new CommandTester($this->command);
    }

    public function tearDown()
    {
        $this->cleanEnvironment();
    }

    public function testCanCreateNewEnvironments()
    {
        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'name' => 'testing'
        ));

        $this->assertRegExp(
            '/Environment created successfully\./',
            $this->commandTester->getDisplay()
        );

        $this->assertFileExists(
            $this->manager->getEnvironmentPath() . '/testing.php',
            'Config file should be created.'
        );
    }

    public function testCantOverwriteConfigFileEnvironments()
    {
        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'name' => 'testing'
        ));

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Environment file already exists');

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'name' => 'testing'
        ));
    }

    public function testCantOverwriteProgrammaticEnvironments()
    {
        $this->manager->addEnvironment('testing');

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Environment is already defined');

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'name' => 'testing'
        ));
    }
}
