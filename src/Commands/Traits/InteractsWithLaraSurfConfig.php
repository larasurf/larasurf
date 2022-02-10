<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use LaraSurf\LaraSurf\Config;

trait InteractsWithLaraSurfConfig
{
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
     * @throws FileNotFoundException
     */
    protected static function larasurfConfig(): Config
    {
        $config = app(Config::class);

        if (!$config->isLoaded()) {
            $config->load(static::laraSurfConfigFilePath());
        }

        return $config;
    }
}
