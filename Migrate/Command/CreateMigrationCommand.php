<?php

namespace Migrate\Command;

use Cocur\Slugify\Slugify;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class CreateMigrationCommand
 *
 * @package Migrate\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class CreateMigrationCommand extends AbstractEnvCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        // TODO: Should the environment and database arguments be optional if the user only has
        //       1 environment and 1 database? It would make the application easier to use for
        //       most small projects, or projects where environments are on different servers.

        $this
            ->setName('migrate:create') // TODO: Rename this command to create:migration
            ->setDescription('Create a SQL migration')
            ->addArgument(
                'db',
                InputArgument::REQUIRED,
                'Database name for the new migration'
            );
    }

    /**
     * Create a migration for the target environment and database.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkEnv();

        $dbName = $input->getArgument('db');

        /* @var $questions QuestionHelper */
        $questions = $this->getHelperSet()->get('question');

        $output->writeln('<comment>Describe what the migration does to make it easier to identify later.</comment>');
        $output->writeln("<comment>Example: 'create users table' or 'add timestamp columns to log table'.</comment>");
        $descriptionQuestion = new Question('Please enter a description: ');
        $description = $questions->ask($input, $output, $descriptionQuestion);

        $slugger = new Slugify();
        $filename = $slugger->slugify($description);
        $timestamp = str_pad(str_replace(".", "", microtime(true)), 14, "0");
        $filename = $timestamp . '_' . $filename . '.sql';

        $templateFile = file_get_contents(__DIR__ . '/../../templates/migration.tpl');
        $templateFile = str_replace('{DESCRIPTION}', $description, $templateFile);

        $migrationFullPath = $this->manager->getMigrationPath($dbName) . '/' . $filename;

        // Ensure the migration path exists for this database.
        if (!file_exists(dirname($migrationFullPath))) {
            mkdir(dirname($migrationFullPath), 0777, true);
        }

        file_put_contents($migrationFullPath, $templateFile);
        $output->writeln("<info>$migrationFullPath created</info>");
    }
}
