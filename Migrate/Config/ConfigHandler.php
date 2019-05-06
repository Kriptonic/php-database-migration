<?php

namespace Migrate\Config;

/**
 * Interface ConfigHandler
 *
 * @package Migrate\Config
 * @author Christopher Sharman <christopher.p.sharman@gmail.com>
 */
interface ConfigHandler
{
    /**
     * Return the configuration.
     *
     * @return array|null The configuration; null if it could not be found.
     */
    public function load();

    /**
     * Save the configuration.
     *
     * @param array $config The configuration to save.
     * @return void
     */
    public function save($config);
}
