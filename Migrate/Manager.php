<?php

namespace Migrate;

use Migrate\Command\AddEnvCommand;
use Migrate\Command\CreateCommand;
use Migrate\Command\DownCommand;
use Migrate\Command\InitCommand;
use Migrate\Command\StatusCommand;
use Migrate\Command\UpCommand;
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
     * The working directory is used as a common root for all application files.
     * @var string The path to the working directory.
     */
    private $workingPath;

    /**
     * Manager constructor.
     *
     * @param string $workingPath The working path for the tool.
     * @param bool $registerDefaultCommands True to register default commands; false otherwise.
     */
    public function __construct($workingPath = 'php_db_migration', $registerDefaultCommands = true)
    {
        $name = 'PHP Database Migration Tool';
        $version = 'UNKNOWN';

        parent::__construct($name, $version);

        if ($registerDefaultCommands) {
            $this->registerCommands();
        }

        $this->workingPath = $workingPath;
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
    public function getEnvPath()
    {
        return $this->getWorkingPath() . '/environments';
    }

    /**
     * Get the migration path.
     *
     * @return string The migration path.
     */
    public function getMigrationsPath()
    {
        return $this->getWorkingPath() . '/migrations';
    }

    /**
     * Register the default commands for the application.
     */
    public function registerCommands()
    {
        $this->add(new CreateCommand());
        $this->add(new DownCommand());
        $this->add(new UpCommand());
        $this->add(new AddEnvCommand());
        $this->add(new StatusCommand());
        $this->add(new InitCommand());
    }
}
