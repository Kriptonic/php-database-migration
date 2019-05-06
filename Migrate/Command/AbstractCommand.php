<?php
/**
 * User: aguidet
 * Date: 27/02/15
 * Time: 17:59
 */

namespace Migrate\Command;

use Migrate\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractCommand
 *
 * @package Migrate\Command
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    /** @var Manager */
    protected $manager;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $application = $this->getApplication();

        // Verify that the application is a Manager - the commands cannot work without it.
        if (!$application instanceof Manager) {
            throw new \RuntimeException('This command can only be run from the Migrate\Manager application');
        }

        // Since most commands will need to use the Manager, we make it available here.
        $this->manager = $application;
    }
}
