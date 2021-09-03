<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use LaraSurf\LaraSurf\Config;

trait InteractsWithLaraSurfConfig
{
    protected static ?Config $larasurf_config = null;

    protected static function laraSurfConfigFilePath()
    {
        return 'larasurf.json';
    }

    protected static function larasurfConfig(): Config
    {
        if (!static::$larasurf_config) {
            static::$larasurf_config = new Config(static::laraSurfConfigFilePath());
        }

        return static::$larasurf_config;
    }
}
