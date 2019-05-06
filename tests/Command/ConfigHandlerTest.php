<?php

namespace Migrate\Test\Command;

use Migrate\Config\ConfigHandlers\PhpConfigHandler;
use Migrate\Manager;

/**
 * Class ConfigHandlerTest
 *
 * @package Migrate\Test\Command
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
class ConfigHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array A default known configuration for use in tests.
     */
    private $defaultConfigArray;

    /**
     * @var string A default known configuration file contents for use in tests.
     */
    private $defaultConfigContent;

    public function setUp()
    {
        parent::setUp();

        $this->defaultConfigArray = array(
            'name' => 'test',
            'description' => 'Testing configuration with this environment',
            'databases' => array(
                'database1' => array(
                    'name' => 'database1',
                    'driver' => 'sqlite',
                    'database' => 'database.sqlite',
                    'host' => null,
                    'port' => null,
                    'username' => null,
                    'password' => null,
                    'charset' => null,
                    'changelog' => 'migration_changelog',
                    'path' => 'path',
                )
            )
        );

        $this->defaultConfigContent = <<<EXPECTED
<?php

/*
 * It is good practice to not version this file.
 *
 * Create a new one in each of your other environments
 * with the correct configuration details.
 */

return array(
    'name' => 'test',
    'description' => 'Testing configuration with this environment',
    'databases' => array(

        'database1' => array(
            // The name will be used to refer to the database on the command-line interface.
            // If you want it to be something other than the database name, specify it here.
            'name' => 'database1',

            // One of the supported pdo_drivers()
            'driver' => 'sqlite',

            // The name of the database to connect to, or path to an sqlite database.
            'database' => 'database.sqlite',

            'host' => null,
            'port' => null,
            'username' => null,
            'password' => null,
            'charset' => null,

            // The name of the table used to record the migrations that have been
            // performed on this database connection.
            'changelog' => 'migration_changelog',

            // A sub folder of the migration path, this will contain the generated
            // migrations for this database.
            'path' => 'path',
        ),

    )
);

EXPECTED;
    }

    public function testManagerSetsDefaultPaths()
    {
        $defaultPath = 'php_db_migration';

        $manager = new Manager();

        $this->assertEquals($defaultPath, $manager->getWorkingPath());
        $this->assertEquals($defaultPath . '/environments', $manager->getEnvironmentPath());
        $this->assertEquals($defaultPath . '/migrations', $manager->getMigrationPath());
    }

    public function testManagerWorkingPathCanBeOverridden()
    {
        $customPath = 'custom_path';

        $manager = new Manager(array(
            'working_path' => 'custom_path'
        ));

        $this->assertEquals($customPath, $manager->getWorkingPath());
        $this->assertEquals($customPath . '/environments', $manager->getEnvironmentPath());
        $this->assertEquals($customPath . '/migrations', $manager->getMigrationPath());
    }

    public function testManagerPathsCanAllBeOverridden()
    {
        $manager = new Manager(array(
            'working_path' => 'working_path',
            'migration_path' => 'migration_path',
            'environment_path' => 'environment_path'
        ));

        $this->assertEquals('working_path', $manager->getWorkingPath());
        $this->assertEquals('migration_path', $manager->getMigrationPath());
        $this->assertEquals('environment_path', $manager->getEnvironmentPath());
    }

    public function testConfigFilesCanBeWritten()
    {
        $configFile = 'config.php';

        $configHandler = new PhpConfigHandler($configFile);
        $configHandler->save($this->defaultConfigArray);

        $this->assertEquals(
            $this->defaultConfigContent,
            file_get_contents($configFile)
        );

        unlink($configFile);
    }

    public function testConfigFilesCanBeLoaded()
    {
        $configFile = 'config.php';

        file_put_contents($configFile, $this->defaultConfigContent);

        $configHandler = new PhpConfigHandler($configFile);
        $config = $configHandler->load();

        $this->assertEquals($this->defaultConfigArray, $config);

        unlink($configFile);
    }

    public function testConfigsCanBeAddedProgrammatically()
    {
        $environmentName = $this->defaultConfigArray['name'];
        $environmentFile = $environmentName . '.php';

        $manager = new Manager();
        $manager->addEnvironment(
            $environmentName,
            $this->defaultConfigArray['description']
        );
        // TODO: Figure out how to specify the name for the database without having to pass a name key with the
        //       configuration details. The name is only required to key the database within the configuration
        //       file and loaded array and ideally does not need to exist within the config its self.
        $manager->addDatabase(
            $environmentName,
            $this->defaultConfigArray['databases']['database1']
        );

        $config = $manager->getEnvironment($environmentName);

        $configHandler = new PhpConfigHandler($environmentFile);
        $configHandler->save($config);

        $this->assertEquals(
            $this->defaultConfigContent,
            file_get_contents($environmentFile)
        );

        unlink($environmentFile);
    }

    public function testExistingEnvironmentCannotBeOverwritten()
    {
        $manager = new Manager();

        // We should have no issues adding different environments.
        $manager->addEnvironment('local');
        $manager->addEnvironment('staging');
        $manager->addEnvironment('some_other_environment');

        // But we shouldn't be able to add one that exists already.
        $this->expectException('RuntimeException');
        $manager->addEnvironment('local');
    }

    public function testExistingDatabaseCannotBeOverwritten()
    {
        $manager = new Manager();

        $manager->addEnvironment('local');
        $manager->addEnvironment('staging');

        // Databases with different names should have no issues being added.
        $manager->addDatabase('local', array(
            'name' => 'db1',
            'driver' => 'sqlite',
            'database' => 'database.sqlite'
        ));

        $manager->addDatabase('local', array(
            'name' => 'db2',
            'driver' => 'sqlite',
            'database' => 'database.sqlite'
        ));

        // We should be able to add a database with a name that was already added
        // if it is added to a different environment.
        $manager->addDatabase('staging', array(
            'name' => 'db1',
            'driver' => 'sqlite',
            'database' => 'database.sqlite'
        ));

        // But we should fail if we add a database with the same name to an
        // environment that has one with the same name already.
        $this->expectException('RuntimeException');

        $manager->addDatabase('local', array(
            'name' => 'db1',
            'driver' => 'sqlite',
            'database' => 'database.sqlite'
        ));
    }

}
