<?php

namespace Migrate\Test\Command;

use Migrate\Command\CreateDatabaseCommand;
use Migrate\Config\ConfigHandlers\PhpConfigHandler;
use Migrate\Manager;
use Migrate\Utils\InputStreamUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class CreateDatabaseCommandTest
 *
 * @package Migrate\Test\Command
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
class CreateDatabaseCommandTest extends AbstractCommandTester
{
    /** @var Manager */
    private $application;

    /** @var Command */
    private $command;

    /** @var CommandTester */
    private $commandTester;

    public function setUp()
    {
        $this->cleanEnvironment();

        $this->createEnvironmentConfiguration();

        // All tests in this file require this basic setup.
        $this->application = new Manager(self::$testManagerConfig);
        $this->application->add(new CreateDatabaseCommand());

        $this->command = $this->application->find('create:db');
        $this->commandTester = new CommandTester($this->command);
    }

    public function tearDown()
    {
        $this->cleanEnvironment();
    }

    /**
     * Retrieve a stream configured to create an sqlite database when run
     * through the CreateDatabaseCommand command.
     *
     * @return bool|resource A configured input stream.
     */
    private function getSqliteDatabaseCommandInputStream()
    {
        $drivers = pdo_drivers();
        $driverIndex = array_search('sqlite', $drivers);

        return InputStreamUtil::fromArray(array(
            $driverIndex, // Driver name
            static::$testDatabaseConfig['database'], // Database path
            '', // Changelog table (use default)
            '', // Connection name (use default)
            '', // Migration path (use default)
        ));
    }

    public function testCanCreateNewDatabase()
    {
        $this->command
            ->getHelper('question')
            ->setInputStream(
                $this->getSqliteDatabaseCommandInputStream()
            );

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'env' => 'testing'
        ));

        // Load in the config file we should have created and verify
        // that it contains what it should.
        $configHandler = new PhpConfigHandler(
            $this->application->getEnvironmentPath() . '/testing.php'
        );

        $config = $configHandler->load();

        $this->assertArrayHasKey(
            static::$testDatabaseConfig['database'], $config['databases'],
            'Database information should exist in the new config file'
        );
    }

    public function testCantOverwriteConfigFileDatabase()
    {
        $userInputStream = $this->getSqliteDatabaseCommandInputStream();

        $this->command
            ->getHelper('question')
            ->setInputStream($userInputStream);

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'env' => 'testing'
        ));

        // The stream must be rewound because we need to use it again.
        rewind($userInputStream);

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('A database with that name already exists in the environment');

        // Run the command again - it should fail this time.
        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'env' => 'testing'
        ));
    }

    public function testCantAddDatabaseToProgrammaticEnvironment()
    {
        // Create an environment and database programmatically.
        // We should not be able to overwrite these.
        $this->application->addEnvironment('programmatic');
        $this->application->addDatabase('programmatic', array(
            'name' => 'programmatic_database',
            'driver' => 'sqlite',
            'database' => 'testing_database.sqlite',
            'host' => null,
            'port' => null,
            'username' => null,
            'password' => null,
            'charset' => null,
            'path' => 'testing_database.sqlite',
        ));

        $this->command
            ->getHelper('question')
            ->setInputStream(
                $this->getSqliteDatabaseCommandInputStream()
            );

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Config file does not exist');

        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
            'env' => 'programmatic'
        ));
    }
}
