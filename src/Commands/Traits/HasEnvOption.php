<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use LaraSurf\LaraSurf\Constants\Cloud;

trait HasEnvOption
{
    use InteractsWithConfig;

    protected function envOption(): string|false
    {
        $env = $this->option('env');

        if (!$env || $env === 'null') {
            $this->error('The --env option is required for this subcommand');

            return false;
        }

        if (!in_array($env, Cloud::ENVIRONMENTS)) {
            $this->error('Invalid --env option given');

            return false;
        }

        if (!static::config()->exists("environments.$env")) {
            $this->error("The '$env' environment is not configured for this environment");

            return false;
        }

        return $env;
    }
}
