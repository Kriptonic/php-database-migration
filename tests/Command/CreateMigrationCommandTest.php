<?php

namespace Migrate\Command;

use Migrate\Manager;
use Migrate\Test\Command\AbstractCommandTester;
use Migrate\Utils\InputStreamUtil;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class CreateMigrationCommandTest
 *
 * @package Migrate\Command
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class CreateMigrationCommandTest extends AbstractCommandTester
{
    public function setUp()
    {
        $this->cleanEnvironment();
        $this->createEnvironmentAndDatabaseConfiguration();
        $this->initialiseEnvironmentDatabase();
    }

    public function tearDown()
    {
        $this->cleanEnvironment();
    }

    public function testExecute()
    {
        $application = new Manager(self::$testManagerConfig);
        $application->add(new CreateMigrationCommand());

        $command = $application->find('migrate:create');
        $commandTester = new CommandTester($command);

        /* @var $question QuestionHelper */
        $question = $command->getHelper('question');
        $question->setInputStream(
            InputStreamUtil::type("my custom migration description\n")
        );

        $commandTester->execute(array(
            'command' => $command->getName(),
            'db' => static::$testDatabaseConfig['database']
        ));

        $matches = array();
        $display = $commandTester->getDisplay();

        preg_match('/.*: (.*) created/', $display, $matches);

        $fileName = $matches[1];

        $this->assertFileExists($fileName);
        $content = file_get_contents($fileName);
        $expected =<<<EXPECTED
-- // my custom migration description\n-- Migration SQL that makes the change goes here.\n\n-- @UNDO\n-- SQL to undo the change goes here.\n
EXPECTED;

        $this->assertEquals($expected, $content);
    }
}
