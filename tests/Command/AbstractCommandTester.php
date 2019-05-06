<?php

namespace Migrate\Test\Command;

use Migrate\Command\CreateDatabaseCommand;
use Migrate\Command\CreateEnvironmentCommand;
use Migrate\Command\InitCommand;
use Migrate\Manager;
use Migrate\Utils\InputStreamUtil;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class AbstractCommandTester
 *
 * @package Migrate\Test\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
abstract class AbstractCommandTester extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array Default testing database configuration.
     */
    public static $testDatabaseConfig = array(
        'driver' => 'sqlite',
        'database' => 'test.sqlite',
        'host' => null,
        'port' => null,
        'charset' => null,
        'username' => null,
        'password' => null,
        'path' => 'test.sqlite',
    );

    /**
     * @var array Default testing Manager configuration.
     */
    public static $testManagerConfig = array(
        'working_path' => 'php_db_migration'
    );

    /**
     * Clean the environment by removing the files created by tests.
     */
    public function cleanEnvironment()
    {
        exec('rm -rf ./' . self::$testManagerConfig['working_path']);

        if (file_exists('test.sqlite')) {
            exec('rm test.sqlite');
        }
    }

    /**
     * Create the test environment and database configuration.
     *
     * @param string $environmentName The name of the environment.
     */
    public function createEnvironmentAndDatabaseConfiguration($environmentName = 'testing')
    {
        $this->createEnvironmentConfiguration($environmentName);
        $this->createDatabaseConfiguration($environmentName);
    }

    /**
     * Create the test environment configuration.
     *
     * @param string $environmentName The environment name.
     */
    public function createEnvironmentConfiguration($environmentName = 'testing')
    {
        $application = new Manager(self::$testManagerConfig);
        $application->add(new CreateEnvironmentCommand());

        $envCommand = $application->find('create:env');
        $envCommandTester = new CommandTester($envCommand);
        $envCommandTester->execute(array(
            'command' => $envCommand->getName(),
            'name' => $environmentName
        ));
    }

    /**
     * Create the test database configuration.
     *
     * @param string $environmentName The target environment name.
     */
    private function createDatabaseConfiguration($environmentName = 'testing')
    {
        $application = new Manager(self::$testManagerConfig);
        $application->add(new CreateDatabaseCommand());

        // Create the input stream required to setup the database we need.
        $inputStream = InputStreamUtil::fromArray(array(
            static::$testDatabaseConfig['driver'],
            static::$testDatabaseConfig['database'],
            '', // Changelog table name (use default)
            '', // Connection name (use default)
            '', // Migration path (use default)
        ));

        $dbCommand = $application->find('create:db');
        $dbCommand->getHelper('question')->setInputStream($inputStream);
        $dbCommandTester = new CommandTester($dbCommand);
        $dbCommandTester->execute(array(
            'command' => $dbCommand->getName(),
            'env' => $environmentName
        ));
    }

    /**
     * Initialise the database.
     */
    public function initialiseEnvironmentDatabase()
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
    }

    /**
     * Create a migration file.
     *
     * @param string $path Path to the migrations folder.
     * @param int $timestamp The migration creation timestamp.
     * @param string $sqlUp The SQL to run when migrating up.
     * @param string $sqlDown The SQL to run when migrating down.
     */
    public function createMigration($path, $timestamp, $sqlUp, $sqlDown)
    {
        $filename = $path . '/' . static::$testDatabaseConfig['path'] . '/' . $timestamp . '_migration.sql';

        $content =<<<SQL
--// unit testing migration
-- Migration SQL that makes the change goes here.
$sqlUp

-- @UNDO
-- SQL to undo the change goes here.
$sqlDown

SQL;

        file_put_contents($filename, $content);
    }
}
