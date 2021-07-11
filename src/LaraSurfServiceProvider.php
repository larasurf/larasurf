<?php

namespace LaraSurf\LaraSurf;

use LaraSurf\LaraSurf\Console\Splash;
use Illuminate\Support\ServiceProvider;

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
            ]);
        }
    }
}
