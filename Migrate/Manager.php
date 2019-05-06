<?php

namespace Migrate;

use Migrate\Command\CreateMigrationCommand;
use Migrate\Command\CreateDatabaseCommand;
use Migrate\Command\CreateEnvironmentCommand;
use Migrate\Command\DownCommand;
use Migrate\Command\InitCommand;
use Migrate\Command\StatusCommand;
use Migrate\Command\UpCommand;
use Migrate\Config\ConfigHandlers\PhpConfigHandler;
use Symfony\Component\Console\Application;

/**
 * Class Manager
 *
 * The Manager class contains configuration settings that can be used by the
 * commands registered with it.
 *
 * @package Migrate
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
class Manager extends Application
{
    /**
     * The working path is used as a common root for all application files.
     * @var string The path to the working directory.
     */
    private $workingPath;

    /**
     * The environment path is used to house un-versioned database connection credentials.
     * @var string The path to the environment directory.
     */
    private $environmentPath;

    /**
     * The migration path is used to house the migration SQL.
     * @var string The path to the migration directory.
     */
    private $migrationPath;

    /**
     * A collection of environment configuration containing database details.
     * This can contain parsed file and programmatic configuration details.
     * @var array Loaded environment configuration.
     */
    private $environments = array();

    /**
     * Manager constructor.
     *
     * @param array $config Configuration.
     * @param bool $registerDefaultCommands True to register default commands; false otherwise.
     */
    public function __construct(array $config = array(), $registerDefaultCommands = true)
    {
        $name = 'PHP Database Migration Tool';
        $version = 'UNKNOWN';

        parent::__construct($name, $version);

        // Set a default working path.
        if (empty($config['working_path'])) {
            $config['working_path'] = 'php_db_migration';
        }

        $this->workingPath = $config['working_path'];

        if (!empty($config['environment_path'])) {
            $this->environmentPath = $config['environment_path'];
        } else {
            $this->environmentPath = $this->workingPath . '/environments';
        }

        if (!empty($config['migration_path'])) {
            $this->migrationPath = $config['migration_path'];
        } else {
            $this->migrationPath = $this->workingPath . '/migrations';
        }

        // In some cases users of this package may not want all of our commands registered.
        // Providing this option allows them to skip our registration and add the ones they want.
        if ($registerDefaultCommands) {
            $this->registerCommands();
        }

        $this->loadConfiguration();
    }

    /**
     * Check to see if an environment exists.
     *
     * @param string $environmentName The name of the environment to check for.
     * @return bool True if the environment exists; false otherwise.
     */
    public function hasEnvironment($environmentName)
    {
        return array_key_exists($environmentName, $this->environments);
    }

    /**
     * Retrieve the configuration for a specific environment.
     *
     * @param string $environmentName The name of the environment.
     * @return array|null An array of environment details; null if environment not found.
     */
    public function getEnvironment($environmentName)
    {
        if (!$this->hasEnvironment($environmentName)) {
            return null;
        }

        return $this->environments[$environmentName];
    }

    /**
     * Load all configuration from the files within the environment path.
     */
    public function loadConfiguration()
    {
        // Find all configuration files in the environment path.
        $configFiles = glob($this->environmentPath . '/*.php');

        foreach ($configFiles as $configFile) {
            $configHandler = new PhpConfigHandler($configFile);
            $config = $configHandler->load();

            $this->environments[$config['name']] = $config;
        }
    }

    /**
     * Add an environment to the Manager.
     *
     * @param string $name The name of the environment.
     * @param null|string $description Optional description for the environment.
     * @return $this The Manager for chaining.
     */
    public function addEnvironment($name, $description = null)
    {
        if (array_key_exists($name, $this->environments)) {
            throw new \RuntimeException('The environment ' . $name . ' already exists');
        }

        $this->environments[$name] = array(
            'name' => $name,
            'description' => $description,
            'databases' => array()
        );

        return $this;
    }

    /**
     * Add database details to the provided environment.
     *
     * @param string $environmentName The name of the environment to add the configuration to.
     * @param array $config The configuration to add to the environment.
     * @return $this The Manager for chaining.
     */
    public function addDatabase($environmentName, array $config)
    {
        if (!array_key_exists($environmentName, $this->environments)) {
            throw new \RuntimeException(
                'Cannot add a database for environment ' . $environmentName . ' which does not exist'
            );
        }

        if (empty($config['driver'])) {
            throw new \InvalidArgumentException('Must provide driver value in $config');
        }

        if (empty($config['database'])) {
            throw new \InvalidArgumentException('Must provide database value in $config');
        }

        if (empty($config['name'])) {
            $config['name'] = $config['database'];
        }

        if (array_key_exists($config['name'], $this->environments[$environmentName]['databases'])) {
            throw new \RuntimeException(
                sprintf(
                    'A database with the name %s already exists in the %s environment',
                    $config['name'],
                    $environmentName
                )
            );
        }

        if (empty($config['path'])) {
            $config['path'] = $config['database'];
        }

        if (empty($config['changelog'])) {
            $config['changelog'] = 'migration_changelog';
        }

        $this->environments[$environmentName]['databases'][$config['name']] = $config;

        return $this;
    }

    /**
     * Get the database configuration for an environment.
     *
     * @param string $environmentName The name of the environment.
     * @param string $connectionName The name of the database connection.
     * @return array|null An array of configuration; null if it cannot be found.
     */
    public function getDatabaseConfig($environmentName, $connectionName)
    {
        if (!array_key_exists($environmentName, $this->environments)) {
            return null;
        }

        if (!array_key_exists($connectionName, $this->environments[$environmentName])) {
            return null;
        }

        return $this->environments[$environmentName][$connectionName];
    }

    /**
     * Get the working path.
     *
     * @return string The working path.
     */
    public function getWorkingPath()
    {
        return $this->workingPath;
    }

    /**
     * Get the environment path.
     *
     * @return string The environment path.
     */
    public function getEnvironmentPath()
    {
        return $this->environmentPath;
    }

    /**
     * Get the migration path.
     *
     * @param null|string $subPath An optional sub-path within the migration path.
     * @return string The migration path.
     */
    public function getMigrationPath($subPath = null)
    {
        if ($subPath) {
            return $this->migrationPath . '/' . $subPath . '/';
        }

        return $this->migrationPath;
    }

    /**
     * Get the path to the configuration file for the provided environment name.
     *
     * @param string $envName The name of environment.
     * @return string The path to the configuration file.
     */
    public function getConfigFilePath($envName)
    {
        return $this->environmentPath . '/' . $envName . '.php';
    }

    /**
     * Register the default commands for the application.
     */
    public function registerCommands()
    {
        $this->add(new CreateMigrationCommand());
        $this->add(new DownCommand());
        $this->add(new UpCommand());
        $this->add(new CreateEnvironmentCommand());
        $this->add(new CreateDatabaseCommand());
        $this->add(new StatusCommand());
        $this->add(new InitCommand());
    }
}
