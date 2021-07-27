<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Facades\File;

trait HasEnvironmentArgument
{
    protected $valid_environments = [
        'stage', 'production',
    ];

    protected function validateEnvironmentArgument()
    {
        if (!in_array($this->argument('environment'), $this->valid_environments)) {
            $this->error('Invalid environment specified');

            return false;
        }

        return true;
    }
}
