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

class AbstractCommand extends Command
{
    /** @var Manager */
    protected $manager;
    protected $mainDir;
    protected $environmentDir;
    protected $migrationDir;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->manager = $this->getApplication();

        if (!$this->manager instanceof Manager) {
            throw new \RuntimeException('This command can only be run from the Migrate\Manager application');
        }

        $this->mainDir = $this->manager->getWorkingPath() . '/';
    }

    /**
     * @return string
     */
    public function getMainDir()
    {
        return $this->mainDir;
    }

    /**
     * @return string
     */
    public function getMigrationDir()
    {
        return $this->manager->getMigrationsPath();
    }

    /**
     * @return string
     */
    public function getEnvironmentDir()
    {
        return $this->manager->getEnvPath();
    }
}
