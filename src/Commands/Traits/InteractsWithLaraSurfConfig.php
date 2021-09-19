<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use LaraSurf\LaraSurf\Config;

trait InteractsWithLaraSurfConfig
{
    /**
     * The LaraSurf configuration file.
     *
     * @var Config|null
     */
    protected static ?Config $larasurf_config = null;

    /**
     * The path to the LaraSurf configuration file.
     *
     * @return string
     */
    protected static function laraSurfConfigFilePath()
    {
        return 'larasurf.json';
    }

    /**
     * Get the JSON decoded LaraSurf configuration file, decoding if not already done.
     *
     * @return Config
     * @throws \JsonException
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected static function larasurfConfig(): Config
    {
        if (!static::$larasurf_config) {
            static::$larasurf_config = new Config(static::laraSurfConfigFilePath());
        }

        return static::$larasurf_config;
    }
}
