<?php

namespace Migrate\Command;

use Migrate\Config\ConfigHandlers\PhpConfigHandler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateEnvironmentCommand
 *
 * @package Migrate\Command
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
class CreateEnvironmentCommand extends AbstractEnvCommand
{
    public function configure()
    {
        $this
            ->setName('create:env')
            ->setDescription('Create a new environment')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Name of the environment to create'
            );
    }

    /**
     * Create an environment config file ready to receive database configuration.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $envName = $input->getArgument('name');

        if ($this->manager->hasEnvironment($envName)) {
            throw new \RuntimeException('Environment is already defined');
        }

        $configFile = $this->manager->getEnvironmentPath() . '/' . $envName . '.php';

        if (file_exists($configFile)) {
            throw new \RuntimeException('Environment file already exists');
        }

        $configHandler = new PhpConfigHandler($configFile);
        $configHandler->save(array(
            'name' => $envName,
            'description' => null,
            'databases' => array()
        ));

        if (file_exists($configFile)) {
            $output->writeln("<info>Environment created successfully.</info>");
        } else {
            $output->writeln("<error>Failed to write environment file, are the permissions correct?</error>");
        }
    }
}
