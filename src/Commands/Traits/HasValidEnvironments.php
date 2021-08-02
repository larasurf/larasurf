<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

trait HasValidEnvironments
{
    protected $valid_environments = [
        'stage', 'production',
    ];
}
