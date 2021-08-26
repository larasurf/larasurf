<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use LaraSurf\LaraSurf\Config;

trait InteractsWithConfig
{
    protected static Config $config;

    protected static function configFileName()
    {
        return 'larasurf.json';
    }

    protected static function config()
    {
        if (!static::$config) {
            static::$config = new Config(static::configFileName());
        }

        return static::$config;
    }
}
