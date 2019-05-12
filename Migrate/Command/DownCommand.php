<?php

namespace Migrate\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class DownCommand
 *
 * @package Migrate\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class DownCommand extends AbstractEnvCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('migrate:down')
            ->setDescription('Rollback all migrations down to [to] option if provided')
            ->addArgument(
                'env',
                InputArgument::REQUIRED,
                'Environment'
            )
            ->addArgument(
                'db',
                InputArgument::REQUIRED,
                'Database'
            )
            ->addOption(
                'to',
                null,
                InputOption::VALUE_REQUIRED,
                'Migration will be downed to this migration id included'
            )
            ->addOption(
                'only',
                null,
                InputOption::VALUE_REQUIRED,
                'If you need to down this migration id only'
            )
            ->addOption(
                'changelog-only',
                null,
                InputOption::VALUE_NONE,
                'Mark as applied without executing SQL '
            )
            ->addOption(
                'remote-only',
                null,
                InputOption::VALUE_REQUIRED,
                "How to handle remote migrations that aren't local",
                'abort'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasOption('remote-only')) {
            $remoteOnlyChoices = array('abort', 'skip', 'upto');
            if (!in_array($input->getOption('remote-only'), $remoteOnlyChoices)) {
                throw new \RuntimeException(
                    'Invalid --remote-only value, use one of: ' . implode(', ', $remoteOnlyChoices)
                );
            }
        }

        $this->checkEnv();

        $this->init($input, $output);

        $changeLogOnly = (bool) $input->getOption('changelog-only');

        $question = $this->getHelper('question');

        $areYouSureQuestion = new Question("Are you sure? <info>(yes/no)</info> <comment>[no]</comment>: ", 'no');
        $areYouSure = $question->ask($input, $output, $areYouSureQuestion);

        if ($areYouSure == 'yes') {
            $toExecute = $this->filterMigrationsToExecute($input, $output);

            if (count($toExecute) == 0) {
                $output->writeln("your database is already up to date");
            } else {
                $progress = new ProgressBar($output, count($toExecute));

                $progress->setFormat(self::$progressBarFormat);
                $progress->setMessage('');
                $progress->start();

                /* @var $migration \Migrate\Migration */
                foreach ($toExecute as $migration) {
                    $progress->setMessage($migration->getDescription());
                    $this->executeDownMigration($migration, $changeLogOnly);
                    $progress->advance();
                }

                $progress->finish();
                $output->writeln("");
            }
        } else {
            $output->writeln("<error>Rollback aborted</error>");
        }
    }
}
