<?php

namespace Migrate\Utils;

/**
 * Class InputStreamUtil
 *
 * @package Migrate\Utils
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class InputStreamUtil
{
    /**
     * Create an input stream from the provided $input.
     *
     * @param string $input A string of text to create a stream from.
     * @return bool|resource A resource if created successfully; false on error.
     */
    public static function type($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input);
        rewind($stream);

        return $stream;
    }

    /**
     * Create an input stream from the provided $input array.
     *
     * @param array $inputArray An array of separate inputs to create a stream from.
     * @return bool|resource A resource if created successfully; false on error.
     */
    public static function fromArray(array $inputArray)
    {
        return static::type(implode("\n", $inputArray) . "\n");
    }
}
