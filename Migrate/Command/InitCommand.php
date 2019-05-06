<?php

namespace Migrate\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class InitCommand
 *
 * @package Migrate\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class InitCommand extends AbstractEnvCommand
{

    protected function configure()
    {
        $this
            ->setName('migrate:init')
            ->setDescription('Create the changelog table on your environment database')
            ->addArgument(
                'env',
                InputArgument::REQUIRED,
                'Environment'
            )
            ->addArgument(
                'db',
                InputArgument::REQUIRED,
                'Database'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);

        $changelog = $this->getChangelogTable();

        $this->getDb()->exec("
            CREATE TABLE $changelog
            (
                id NUMERIC(20,0),
                applied_at CHARACTER VARYING(25),
                version CHARACTER VARYING(25),
                description CHARACTER VARYING(255)
            )
        ");

        $output->writeln("changelog table ($changelog) successfully created");
    }
}
