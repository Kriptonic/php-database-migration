<?php

namespace Migrate\Command;

use Migrate\Migration;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StatusCommand
 *
 * @package Migrate\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class StatusCommand extends AbstractEnvCommand
{

    protected function configure()
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Display the current status of the specified environment')
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

        $table = new Table($output);
        $table->setHeaders(array('id', 'version', 'applied at', 'description'));

        $migrations = $this->getRemoteAndLocalMigrations();

        /* @var $migration Migration */
        foreach ($migrations as $migration) {
            $table->addRow($migration->toArray());
        }

        $table->render();
    }
}
