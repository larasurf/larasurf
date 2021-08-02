<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

trait HasEnvironmentArgument
{
    protected function validateEnvironmentArgument()
    {
        if (!in_array($this->argument('environment'), $this->valid_environments)) {
            $this->error('Invalid environment specified');

            return false;
        }

        return true;
    }
}
