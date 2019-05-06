<?php

namespace Command;

use Migrate\Command\DownCommand;
use Migrate\Command\StatusCommand;
use Migrate\Command\UpCommand;
use Migrate\Manager;
use Migrate\Test\Command\AbstractCommandTester;
use Migrate\Utils\InputStreamUtil;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class UpDownCommandTest
 *
 * @package Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class UpDownCommandTest extends AbstractCommandTester
{
    /** @var Manager */
    private $manager;

    public function setUp()
    {
        $this->cleanEnvironment();
        $this->createEnvironmentAndDatabaseConfiguration();
        $this->initialiseEnvironmentDatabase();

        $this->manager = new Manager(static::$testManagerConfig);
        $this->manager->add(new UpCommand());
        $this->manager->add(new DownCommand());
        $this->manager->add(new StatusCommand());

        $this->createMigration(
            $this->manager->getMigrationPath(),
            '0',
            'CREATE TABLE test (id INTEGER, thevalue TEXT);',
            'DROP TABLE test;'
        );

        $this->createMigration(
            $this->manager->getMigrationPath(),
            '1',
            'SELECT 1',
            'DELETE FROM test WHERE id = 1;'
        );

        $this->createMigration(
            $this->manager->getMigrationPath(),
            '2',
            "INSERT INTO test VALUES (2, 'two');",
            'DELETE FROM test WHERE id = 2;'
        );
    }

    public function tearDown()
    {
        $this->cleanEnvironment();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testUpMigrationWithError()
    {
        $this->createMigration($this->manager->getMigrationPath(), '3', 'SELECT ;', 'SELECT ;');
        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing'
        ));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDownMigrationWithError()
    {
        $this->createMigration($this->manager->getMigrationPath(), '3', 'SELECT 1;', 'SELECT ;');


        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing'
        ));

        $command = $this->manager->find('migrate:down');
        $commandTester = new CommandTester($command);

        /* @var $question QuestionHelper */
        $question = $command->getHelper('question');
        $question->setInputStream(InputStreamUtil::type("yes\n"));

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing'
        ));
    }

    public function testUpAllPendingMigrations()
    {

        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $expected =<<<EXPECTED
connected
0/3 [>---------------------------] 0 % []
1/3 [=========>------------------] 33 % [migration]
2/3 [==================>---------] 66 % [migration]
3/3 [============================] 100 % [migration]

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());
    }

    public function testDownLastMigration()
    {
        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));



        $command = $this->manager->find('migrate:down');
        $commandTester = new CommandTester($command);

        /* @var $question QuestionHelper */
        $question = $command->getHelper('question');
        $question->setInputStream(InputStreamUtil::type("yes\n"));

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $expected =<<<EXPECTED
connected
Are you sure? (yes/no) [no]: 0/1 [>---------------------------] 0 % []
1/1 [============================] 100 % [migration]

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());
    }

    public function testUpOnly()
    {
        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database'],
            '--only' => '1'
        ));

        $expected =<<<EXPECTED
connected
0/1 [>---------------------------] 0 % []
1/1 [============================] 100 % [migration]

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());

        $command = $this->manager->find('migrate:status');
        $commandTester = new CommandTester($command);

        $currentDate = date('Y-m-d H:i:s');

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));


        $expected =<<<EXPECTED
connected
+----+---------+---------------------+-------------+
| id | version | applied at          | description |
+----+---------+---------------------+-------------+
| 0  |         |                     | migration   |
| 1  |         | $currentDate | migration   |
| 2  |         |                     | migration   |
+----+---------+---------------------+-------------+

EXPECTED;

        // TODO: This sometimes fails due to a second change between the date time string
        //       being calculated and the migration being run. It may be worth calculating
        //       and comparing the second after the calculated one as well to prevent this.
        $this->assertEquals($expected, $commandTester->getDisplay());
    }

    public function testDownOnly()
    {
        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $command = $this->manager->find('migrate:down');
        $commandTester = new CommandTester($command);

        /* @var $question QuestionHelper */
        $question = $command->getHelper('question');
        $question->setInputStream(InputStreamUtil::type("yes\n"));

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database'],
            '--only' => '1'
        ));

        $expected =<<<EXPECTED
connected
Are you sure? (yes/no) [no]: 0/1 [>---------------------------] 0 % []
1/1 [============================] 100 % [migration]

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());

        $command = $this->manager->find('migrate:status');
        $commandTester = new CommandTester($command);

        $currentTime = time();
        $validDates = array();
        foreach (range(-1, 1) as $i) {
            $validDates[] = date('Y-m-d H:i:s', $currentTime + $i);
        }
        $dateRegex = '(' . implode('|', $validDates) . ') *';

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $expected =<<<'EXPECTED'
connected
+----+---------+---------------------+-------------+
| id | version | applied at          | description |
+----+---------+---------------------+-------------+
| 0  |         | DATE_REGEX          | migration   |
| 1  |         |                     | migration   |
| 2  |         | DATE_REGEX          | migration   |
+----+---------+---------------------+-------------+

EXPECTED;

        $pattern = '/^' . preg_quote($expected, '/') . '$/';
        $pattern = preg_replace('/DATE_REGEX */', $dateRegex, $pattern);

        $this->assertRegExp($pattern, $commandTester->getDisplay());

    }

    public function testUpTo()
    {
        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database'],
            '--to' => '1'
        ));

        $expected =<<<EXPECTED
connected
0/2 [>---------------------------] 0 % []
1/2 [==============>-------------] 50 % [migration]
2/2 [============================] 100 % [migration]

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());

        $command = $this->manager->find('migrate:status');
        $commandTester = new CommandTester($command);

        $currentDate = date('Y-m-d H:i:s');

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));


        $expected =<<<EXPECTED
connected
+----+---------+---------------------+-------------+
| id | version | applied at          | description |
+----+---------+---------------------+-------------+
| 0  |         | $currentDate | migration   |
| 1  |         | $currentDate | migration   |
| 2  |         |                     | migration   |
+----+---------+---------------------+-------------+

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());
    }

    public function testDownTo()
    {
        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $command = $this->manager->find('migrate:down');
        $commandTester = new CommandTester($command);

        /* @var $question QuestionHelper */
        $question = $command->getHelper('question');
        $question->setInputStream(InputStreamUtil::type("yes\n"));

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database'],
            '--to' => '1'
        ));

        $expected =<<<EXPECTED
connected
Are you sure? (yes/no) [no]: 0/2 [>---------------------------] 0 % []
1/2 [==============>-------------] 50 % [migration]
2/2 [============================] 100 % [migration]

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());

        $command = $this->manager->find('migrate:status');
        $commandTester = new CommandTester($command);

        $currentDate = date('Y-m-d H:i:s');

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));


        $expected =<<<EXPECTED
connected
+----+---------+---------------------+-------------+
| id | version | applied at          | description |
+----+---------+---------------------+-------------+
| 0  |         | $currentDate | migration   |
| 1  |         |                     | migration   |
| 2  |         |                     | migration   |
+----+---------+---------------------+-------------+

EXPECTED;

        $this->assertEquals($expected, $commandTester->getDisplay());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage you are not in an initialized php-database-migration directory
     */
    public function testUpInANotInitializedDirectory()
    {
        $this->cleanEnvironment();

        $command = $this->manager->find('migrate:up');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $command = $this->manager->find('migrate:down');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database'],
            '--to' => '1'
        ));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage you are not in an initialized php-database-migration directory
     */
    public function testDownInANotInitializedDirectory()
    {
        $this->cleanEnvironment();

        $command = $this->manager->find('migrate:down');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database']
        ));

        $command = $this->manager->find('migrate:down');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
            'env' => 'testing',
            'db' => static::$testDatabaseConfig['database'],
            '--to' => '1'
        ));
    }
}
