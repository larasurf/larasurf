<?php

namespace LaraSurf\LaraSurf;

use Illuminate\Support\ServiceProvider;
use LaraSurf\LaraSurf\Commands\Configure;
use LaraSurf\LaraSurf\Commands\Env;
use LaraSurf\LaraSurf\Commands\Infra;
use LaraSurf\LaraSurf\Commands\Publish;
use LaraSurf\LaraSurf\Commands\Splash;

class LaraSurfServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Splash::class,
                Publish::class,
                Infra::class,
                Env::class,
                Configure::class,
            ]);
        }
    }
}
