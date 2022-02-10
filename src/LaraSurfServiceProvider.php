<?php

namespace LaraSurf\LaraSurf;

use Illuminate\Support\ServiceProvider;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\CloudWatchLogsClient;
use LaraSurf\LaraSurf\AwsClients\Ec2Client;
use LaraSurf\LaraSurf\AwsClients\EcsClient;
use LaraSurf\LaraSurf\AwsClients\RdsClient;
use LaraSurf\LaraSurf\AwsClients\Route53Client;
use LaraSurf\LaraSurf\AwsClients\SesClient;
use LaraSurf\LaraSurf\AwsClients\SsmClient;
use LaraSurf\LaraSurf\CircleCI\Client as CircleCIClient;
use LaraSurf\LaraSurf\Commands\CircleCI;
use LaraSurf\LaraSurf\Commands\CloudArtisan;
use LaraSurf\LaraSurf\Commands\CloudDomains;
use LaraSurf\LaraSurf\Commands\CloudEmails;
use LaraSurf\LaraSurf\Commands\CloudImages;
use LaraSurf\LaraSurf\Commands\CloudIngress;
use LaraSurf\LaraSurf\Commands\CloudStacks;
use LaraSurf\LaraSurf\Commands\CloudTasks;
use LaraSurf\LaraSurf\Commands\CloudUsers;
use LaraSurf\LaraSurf\Commands\CloudVars;
use LaraSurf\LaraSurf\Commands\Config;
use LaraSurf\LaraSurf\Commands\ConfigureNewEnvironments;
use LaraSurf\LaraSurf\Commands\Publish;
use LaraSurf\LaraSurf\Commands\Splash;

class LaraSurfServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register()
    {
        $this->app->singleton(\LaraSurf\LaraSurf\Config::class, function ($app) {
            return new \LaraSurf\LaraSurf\Config();
        });

        $this->app->bind(AcmClient::class, function ($app) {
            return new AcmClient();
        });
        $this->app->bind(CircleCIClient::class, function ($app) {
            return new CircleCIClient();
        });

        $this->app->bind(CloudFormationClient::class, function ($app) {
            return new CloudFormationClient();
        });

        $this->app->bind(CloudWatchLogsClient::class, function ($app) {
            return new CloudWatchLogsClient();
        });

        $this->app->bind(CloudWatchLogsClient::class, function ($app) {
            return new CloudWatchLogsClient();
        });

        $this->app->bind(Ec2Client::class, function ($app) {
            return new Ec2Client();
        });

        $this->app->bind(EcsClient::class, function ($app) {
            return new EcsClient();
        });

        $this->app->bind(RdsClient::class, function ($app) {
            return new RdsClient();
        });

        $this->app->bind(Route53Client::class, function ($app) {
            return new Route53Client();
        });

        $this->app->bind(SchemaCreator::class, function ($app) {
            return new SchemaCreator();
        });

        $this->app->bind(SesClient::class, function ($app) {
            return new SesClient();
        });

        $this->app->bind(SsmClient::class, function ($app) {
            return new SsmClient();
        });
    }

    /**
     * Boot the application.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Splash::class,
                Publish::class,
                Config::class,
                CircleCI::class,
                CloudDomains::class,
                CloudEmails::class,
                CloudIngress::class,
                CloudStacks::class,
                CloudVars::class,
                CloudImages::class,
                CloudUsers::class,
                CloudArtisan::class,
                CloudTasks::class,
                ConfigureNewEnvironments::class,
            ]);
        }
    }
}
