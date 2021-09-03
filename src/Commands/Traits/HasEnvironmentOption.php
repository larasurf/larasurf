<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use LaraSurf\LaraSurf\Constants\Cloud;

trait HasEnvironmentOption
{
    use InteractsWithLaraSurfConfig;

    protected function environmentOption(): string|false
    {
        $env = $this->option('environment');

        if (!$env || $env === 'null') {
            $this->error('The --environment option is required for this subcommand');

            return false;
        }

        if (!in_array($env, Cloud::ENVIRONMENTS)) {
            $this->error('Invalid --environment option given');

            return false;
        }

        if (!static::config()->exists("environments.$env")) {
            $this->error("The '$env' environment is not configured for this project");

            return false;
        }

        return $env;
    }
}
