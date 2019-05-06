<?php

namespace Migrate\Config\ConfigHandlers;

use Migrate\Config\ConfigHandler;

/**
 * Class PhpConfigHandler
 *
 * @package Migrate\Config\ConfigHandlers
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
class PhpConfigHandler implements ConfigHandler
{
    /**
     * This template contains the text for the base file and has a replacement tag for
     * the database templates we generate for each database in this environment.
     * @var string File template text.
     */
    private static $fileTemplate;

    /**
     * This template contains the text for the database content and contains substitution
     * tags. This template will be repeated for each database within the configuration.
     * @var string Database template text.
     */
    private static $databaseTemplate;

    /**
     * @var string Path to the configuration file.
     */
    private $configPath;

    /**
     * PhpConfigHandler constructor.
     *
     * @param string $configFile The path to the configuration file.
     */
    public function __construct($configFile)
    {
        $this->configPath = $configFile;

        // Retrieve the templates for use when exporting the configuration.
        $templatePath = __DIR__ . '/../../../templates/php/';
        static::$fileTemplate = file_get_contents($templatePath . '/file.tpl');
        static::$databaseTemplate = file_get_contents($templatePath . '/database.tpl');
    }

    /**
     * Return the configuration.
     *
     * @return array The configuration.
     */
    public function load()
    {
        if (!file_exists($this->configPath)) {
            throw new \RuntimeException('Config file does not exist: ' . $this->configPath);
        }

        return require $this->configPath;
    }

    /**
     * Save the content of the config.
     *
     * @param array $config The configuration to save.
     * @return void
     */
    public function save($config)
    {
        $configFile = static::$fileTemplate;

        foreach (array('name', 'description') as $key) {
            $configFile = str_replace('{' . $key . '}', $config[$key], $configFile);
        }

        $compiledDatabases = array();

        foreach ($config['databases'] as $name => $database) {
            $compiledDatabases[] = $this->getGeneratedDatabaseString($name, $database);
        }

        $databasesString = '';

        if (count($compiledDatabases)) {
            $databasesString = implode("\n", $compiledDatabases);
        }

        $configContent = str_replace(
            '{databases}',
            $databasesString,
            $configFile
        );

        $directory = dirname($this->configPath);

        // Recursively create the config's file location in case it doesn't exist yet.
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->configPath, $configContent);
    }

    /**
     * Generate a string for the configuration file using the provided database configuration.
     *
     * @param string $name The database connection name.
     * @param array $database The database configuration details.
     * @return string The configuration string for the provided database.
     */
    private function getGeneratedDatabaseString($name, $database)
    {
        $template = str_replace('{name}', $name, static::$databaseTemplate);

        foreach ($database as $key => $value) {
            $template = str_replace(
                '{' . $key . '}',
                "'" . $value . "'",
                $template
            );
        }

        // Any replacements that weren't parsed out by this point aren't going to be.
        // Replace them with null (else we'll have a syntax error in our configs when we load them).
        return preg_replace("/{\w+}|''/", 'null', $template);
    }
}
