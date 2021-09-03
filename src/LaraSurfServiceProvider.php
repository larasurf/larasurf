<?php

namespace LaraSurf\LaraSurf;

use Illuminate\Support\ServiceProvider;
use LaraSurf\LaraSurf\Commands\CloudDomains;
use LaraSurf\LaraSurf\Commands\CloudEmails;
use LaraSurf\LaraSurf\Commands\CloudImages;
use LaraSurf\LaraSurf\Commands\CloudIngress;
use LaraSurf\LaraSurf\Commands\CloudStacks;
use LaraSurf\LaraSurf\Commands\CloudUsers;
use LaraSurf\LaraSurf\Commands\CloudVars;
use LaraSurf\LaraSurf\Commands\Config;
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
                Config::class,
                CloudDomains::class,
                CloudEmails::class,
                CloudIngress::class,
                CloudStacks::class,
                CloudVars::class,
                CloudImages::class,
                CloudUsers::class,
            ]);
        }
    }
}
