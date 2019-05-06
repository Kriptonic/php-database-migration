<?php

namespace Migrate\Command;

use Migrate\Config\ConfigHandlers\PhpConfigHandler;
use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class CreateDatabaseCommand
 *
 * @package Migrate\Command
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
class CreateDatabaseCommand extends AbstractEnvCommand
{
    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    /** @var QuestionHelper */
    private $question;

    protected function configure()
    {
        $this
            ->setName('create:db')
            ->setDescription('Create a new database')
            ->addArgument(
                'env',
                InputArgument::REQUIRED,
                'Name of the environment to create'
            );
    }

    /**
     * Add a database connection to an existing environment.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->question = $this->getHelperSet()->get('question');

        $envName = $input->getArgument('env');

        if (!$this->manager->hasEnvironment($envName)) {
            throw new RuntimeException('Environment does not exist');
        }

        $driver = $this->askDatabaseDriver();

        // In some cases the user will not need to be asked every question.
        if ($driver === 'sqlite') {
            $dbName = $this->askDatabaseFile();
            $dbHost = $dbPort = $dbUser = $dbPass = $dbChar = null;
        } else {
            $dbName = $this->askDatabaseName();
            $dbHost = $this->askDatabaseHost();

            // TODO: Should we try and determine the default port for the selected driver and pass it as a default?
            $dbPort = $this->askDatabasePort();
            $dbUser = $this->askDatabaseUsername();
            $dbPass = $this->askDatabasePassword();
            $dbChar = $this->askDatabaseCharset();
        }

        $output->writeln(array(
            '<comment>The changelog table records the migrations that have been performed on the database.</comment>',
            '<comment>You can change the default value if it conflicts.</comment>'
        ));

        $changelogTable = $this->askDatabaseChangelogTable();

        $output->writeln(array(
            '<comment>What name would you like to give to the connection?</comment>',
            '<comment>You will refer to this database configuration on the command-line with this name.</comment>'
        ));

        $connectionName = $this->askConnectionName($dbName);

        $output->writeln(array(
            '<comment>Migration files for this connection will be created within a sub-folder.</comment>',
            '<comment>You may override the default (connection name).</comment>'
        ));

        $path = $this->askMigrationPath($connectionName);

        $configFile = $this->manager->getEnvironmentPath() . '/' . $envName . '.php';
        $configHandler = new PhpConfigHandler($configFile);
        $existingConfig = $configHandler->load();

        if (!array_key_exists('databases', $existingConfig)) {
            throw new RuntimeException('Existing configuration is malformed');
        }

        if (array_key_exists($connectionName, $existingConfig['databases'])) {
            throw new RuntimeException('A database with that name already exists in the environment');
        }

        $existingConfig['databases'][$connectionName] = array(
            'name' => $envName,
            'driver' => $driver,
            'database' => $dbName,
            'host' => $dbHost,
            'port' => $dbPort,
            'username' => $dbUser,
            'password' => $dbPass,
            'charset' => $dbChar,
            'changelog' => $changelogTable,
            'path' => $path
        );

        $configHandler->save($existingConfig);

        $output->writeln('<info>Configuration updated.</info>');
    }

    /**
     * Ask for the database driver.
     *
     * @return string Chosen database driver.
     */
    private function askDatabaseDriver()
    {
        $drivers = pdo_drivers();
        $question = new ChoiceQuestion('Please choose your pdo driver', $drivers);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database file.
     *
     * @return string Chosen database file.
     */
    private function askDatabaseFile()
    {
        $question = new Question('Please enter the path to the database file: ', null);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database host.
     *
     * @return string Chosen database host.
     */
    private function askDatabaseHost()
    {
        $question = new Question('Please enter the database host: ', null);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database port.
     *
     * @param null|int $default The default value to use; null otherwise.
     * @return string Chosen database port.
     */
    private function askDatabasePort($default = null)
    {
        $questionText = 'Please enter the database port';

        if ($default) {
            $questionText .= ' <info>(default ' . $default . ')</info> ';
        }

        $question = new Question($questionText . ': ', $default);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database name.
     *
     * @return string Chosen database name.
     */
    private function askDatabaseName()
    {
        $question = new Question('Please enter the database name: ', null);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database user name.
     *
     * @return string Chosen database user name.
     */
    private function askDatabaseUsername()
    {
        $question = new Question('Please enter the database user name: ', null);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database user password.
     *
     * @return string Chosen database user password.
     */
    private function askDatabasePassword()
    {
        $question = new Question('Please enter the database password: ', null);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database charset.
     *
     * @return string Chosen database charset.
     */
    private function askDatabaseCharset()
    {
        $question = new Question('Please enter the database charset: ', null);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the database changelog table.
     *
     * @return string Chosen database changelog table.
     */
    private function askDatabaseChangelogTable()
    {
        $question = new Question('Please enter the changelog table <info>(default changelog)</info>: ', 'changelog');
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the migration sub-path.
     *
     * @param null|int $default The default value to use; null otherwise.
     * @return string Chosen migration sub-path.
     */
    private function askMigrationPath($default)
    {
        $question = new Question('Enter the migration path <info>(default ' . $default . ')</info>: ', $default);
        return $this->question->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for the connection name.
     *
     * @param null|int $default The default value to use; null otherwise.
     * @return string Chosen connection name.
     */
    private function askConnectionName($default)
    {
        $question = new Question(
            'Please enter the name for this database connection <info>(default ' . $default . ')</info>: ',
            $default
        );
        return $this->question->ask($this->input, $this->output, $question);
    }
}
