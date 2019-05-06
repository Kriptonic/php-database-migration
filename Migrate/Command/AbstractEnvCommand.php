<?php

namespace Migrate\Command;

use Migrate\Config\ConfigHandlers\PhpConfigHandler;
use Migrate\Manager;
use Migrate\Migration;
use Migrate\Utils\ArrayUtil;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractEnvCommand
 *
 * @package Migrate\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
abstract class AbstractEnvCommand extends AbstractCommand
{
    /**
     * @var string The migrating database progress bar.
     */
    protected static $progressBarFormat = '%current%/%max% [%bar%] %percent% % [%message%]';

    /**
     * @var \PDO Database connection.
     */
    private $db;

    /**
     * @var array
     */
    private $dbConfig;

    /**
     * Get the configured PDO object.
     *
     * @return \PDO The configured PDO object.
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Get the entire database configuration or a key from it, if specified.
     *
     * @param string|null $key The key to receive from the database configuration; or null for all keys.
     * @return array|string An array of all values if $key was null; otherwise value of the provided $key.
     */
    public function getDatabaseConfig($key = null)
    {
        if ($key !== null) {
            return $this->dbConfig[$key];
        }

        return $this->dbConfig;
    }

    /**
     * Get the changelog table name.
     *
     * @return string|null The name of the changelog table; null if not defined.
     */
    public function getChangelogTable()
    {
        return ArrayUtil::get($this->getDatabaseConfig(), 'changelog');
    }

    /**
     * Check to see if the environment has been defined.
     */
    protected function checkEnv()
    {
        if (!file_exists($this->manager->getEnvironmentPath())) {
            throw new \RuntimeException("you are not in an initialized php-database-migration directory");
        }
    }

    /**
     * Initialise the environment by creating directories, retireving the configuration, and creating
     * the configured PDO object.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        $envName = $input->getArgument('env');
        $dbConnectionName = $input->getArgument('db');

        if (!file_exists($this->manager->getWorkingPath())) {
            mkdir($this->manager->getWorkingPath(), 0777, true);
        }

        if (!file_exists($this->manager->getEnvironmentPath())) {
            mkdir($this->manager->getEnvironmentPath(), 0777, true);
        }

        if (!file_exists($this->manager->getMigrationPath($dbConnectionName))) {
            mkdir($this->manager->getMigrationPath($dbConnectionName), 0777, true);
        }

        $configHandler = new PhpConfigHandler(
            $this->manager->getConfigFilePath($envName)
        );

        $config = $configHandler->load();

        if (!array_key_exists($dbConnectionName, $config['databases'])) {
            $didFind = implode(', ', array_keys($config['databases']));
            $message = "Connection name '{$dbConnectionName}' not found in configuration, did find: {$didFind}";
            $e = new \RuntimeException($message);
            print $e->getTraceAsString();
            throw $e;
        }

        $dbConfig = $config['databases'][$dbConnectionName];
        $this->dbConfig = $dbConfig;

        $driver = ArrayUtil::get($dbConfig, 'driver');
        $port = ArrayUtil::get($dbConfig, 'port');
        $host = ArrayUtil::get($dbConfig, 'host');
        $dbname = ArrayUtil::get($dbConfig, 'database');
        $username = ArrayUtil::get($dbConfig, 'username');
        $password = ArrayUtil::get($dbConfig, 'password');
        $charset = ArrayUtil::get($dbConfig, 'charset');

        $uri = $driver;

        if ($driver == 'sqlite') {
            $uri .= ":$dbname";
        } else {
            $uri .= ($dbname === null) ? '' : ":dbname=$dbname";
            $uri .= ($host === null) ? '' : ";host=$host";
            $uri .= ($port === null) ? '' : ";port=$port";
            $uri .= ($charset === null) ? '' : ";charset=$charset";
        }
        $this->db = new \PDO(
            $uri,
            $username,
            $password,
            array()
        );

        $output->writeln('<info>connected</info>');
    }

    /**
     * Retrieve a list of the local migrations.
     *
     * @return array|Migration[] An array of migrations.
     */
    public function getLocalMigrations()
    {
        $migrationPath = $this->manager->getMigrationPath(
            $this->getDatabaseConfig('path')
        );

        $fileList = scandir($migrationPath);
        $fileList = ArrayUtil::filter($fileList);

        $migrations = array();
        foreach ($fileList as $file) {
            $migration = Migration::createFromFile($file, $migrationPath);
            $migrations[$migration->getId()] = $migration;
        }

        ksort($migrations);

        return $migrations;
    }

    /**
     * Retrieve a list of the remote migrations.
     *
     * @return array|Migration[] An array of migrations.
     */
    public function getRemoteMigrations()
    {
        $migrationPath = $this->manager->getMigrationPath(
            $this->getDatabaseConfig('path')
        );

        $migrations = array();
        $result = $this->getDb()->query("SELECT * FROM {$this->getChangelogTable()} ORDER BY id");
        if ($result) {
            foreach ($result as $row) {
                $migration = Migration::createFromRow($row, $migrationPath);
                if ($migration !== null) {
                    $migrations[$migration->getId()] = $migration;
                }
            }

            ksort($migrations);
        }
        return $migrations;
    }

    /**
     * Retrieve a list of all known migrations, local and remote.
     *
     * @return array|Migration[] An array of migrations.
     */
    public function getRemoteAndLocalMigrations()
    {
        $local = $this->getLocalMigrations();
        $remote = $this->getRemoteMigrations();

        foreach ($remote as $aRemote) {
            $local[$aRemote->getId()] = $aRemote;
        }

        ksort($local);

        return $local;
    }

    /**
     * Retrieve a list of local migrations that haven't been run on the remote.
     *
     * @return array|Migration[] An array of migrations.
     */
    public function getToUpMigrations()
    {
        $locales = $this->getLocalMigrations();
        $remotes = $this->getRemoteMigrations();

        foreach ($remotes as $remote) {
            unset($locales[$remote->getId()]);
        }

        ksort($locales);

        return $locales;
    }

    /**
     * Retrieve a list of migrations that have been run on the server most recently run first.
     *
     * @return array|Migration[] An array of migrations.
     */
    public function getToDownMigrations()
    {
        $remotes = $this->getRemoteMigrations();

        ksort($remotes);

        $remotes = array_reverse($remotes, true);

        return $remotes;
    }

    /**
     * Save a migration's details to the database changelog table.
     *
     * @param Migration $migration The migration to record in the database.
     */
    public function saveToChangelog(Migration $migration)
    {
        $appliedAt = date('Y-m-d H:i:s');
        $sql = "INSERT INTO {$this->getChangelogTable()}
          (id, version, applied_at, description)
          VALUES
          ({$migration->getId()},'{$migration->getVersion()}','{$appliedAt}','{$migration->getDescription()}');
        ";
        $result = $this->getDb()->exec($sql);

        if (! $result) {
            throw new \RuntimeException("changelog table has not been initialized");
        }
    }

    /**
     * Remove an entry from the database changelog table for the provided Migration.
     *
     * @param Migration $migration The migration to remove from the changelog table.
     */
    public function removeFromChangelog(Migration $migration)
    {
        $sql = "DELETE FROM {$this->getChangelogTable()} WHERE id = {$migration->getId()}";
        $result = $this->getDb()->exec($sql);
        if (! $result) {
            throw new \RuntimeException("Impossible to delete migration from changelog table");
        }
    }

    /**
     * Run the Up portion of a Migration.
     *
     * @param Migration $migration The migration to migrate up.
     * @param bool $changeLogOnly True if we want to only report that this migration happened; false otherwise.
     */
    public function executeUpMigration(Migration $migration, $changeLogOnly = false)
    {
        $this->getDb()->beginTransaction();

        if ($changeLogOnly === false) {
            $result = $this->getDb()->exec($migration->getSqlUp());

            if ($result === false) {
                // error while executing the migration
                $errorInfo = "";
                $errorInfos = $this->getDb()->errorInfo();
                foreach ($errorInfos as $line) {
                    $errorInfo .= "\n$line";
                }
                $this->getDb()->rollBack();
                throw new \RuntimeException(sprintf(
                    "migration error, some SQL may be wrong\n\nid: %s\nfile: %s\n %s",
                    $migration->getId(),
                    $migration->getFile(),
                    $errorInfo
                ));
            }
        }

        $this->saveToChangelog($migration);
        $this->getDb()->commit();
    }

    /**
     * Run the Down portion of a Migration.
     *
     * @param Migration $migration The migration to migrate down.
     * @param bool $changeLogOnly True if we want to only report that this migration happened; false otherwise.
     */
    public function executeDownMigration(Migration $migration, $changeLogOnly = false)
    {
        $this->getDb()->beginTransaction();

        if ($changeLogOnly === false) {
            $result = $this->getDb()->exec($migration->getSqlDown());

            if ($result === false) {
                // error while executing the migration
                $errorInfo = "";
                $errorInfos = $this->getDb()->errorInfo();
                foreach ($errorInfos as $line) {
                    $errorInfo .= "\n$line";
                }
                $this->getDb()->rollBack();
                throw new \RuntimeException(sprintf(
                    "migration error, some SQL may be wrong\n\nid: %s\nfile: %s\n%s\n",
                    $migration->getId(),
                    $migration->getFile(),
                    $errorInfo
                ));
            }
        }
        $this->removeFromChangelog($migration);
        $this->getDb()->commit();
    }

    /**
     * Filter the migration to ensure we are only running the correct ones.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array|Migration[] The filtered migrations.
     */
    protected function filterMigrationsToExecute(InputInterface $input, OutputInterface $output)
    {
        $down = false;

        if (strpos($this->getName(), 'up') > 0) {
            $toExecute = $this->getToUpMigrations();
        } else {
            $down = true;
            $toExecute = $this->getToDownMigrations();
        }

        $only = $input->getOption('only');
        if ($only !== null) {
            if (! array_key_exists($only, $toExecute)) {
                throw new \RuntimeException("Impossible to execute migration $only!");
            }
            $theMigration = $toExecute[$only];
            $toExecute = array($theMigration->getId() => $theMigration);
        }

        $to = $input->getOption('to');
        if ($to !== null) {
            if (! array_key_exists($to, $toExecute)) {
                throw new \RuntimeException("Target migration $to does not exist or has already been executed/downed!");
            }

            $temp = $toExecute;
            $toExecute = array();
            foreach ($temp as $migration) {
                $toExecute[$migration->getId()] = $migration;
                if ($migration->getId() == $to) {
                    break;
                }
            }
        } elseif ($down && count($toExecute) > 1) {
            // WARNING DOWN SPECIAL TREATMENT
            // we dont want all the database to be downed because
            // of a bad command!
            $theMigration = array_shift($toExecute);
            $toExecute = array($theMigration->getId() => $theMigration);
        }

        return $toExecute;
    }
}
