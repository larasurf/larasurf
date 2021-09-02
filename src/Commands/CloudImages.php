<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Constants\Cloud;

class CloudImages extends Command
{
    use HasSubCommands;
    use InteractsWithAws;
    use HasEnvironmentOption;

    const REPOSITORY_TYPE_APPLICATION = 'application';
    const REPOSITORY_TYPE_WEBSERVER = 'webserver';

    const COMMAND_CREATE_REPOSITORIES = 'create-repositories';
    const COMMAND_DELETE_REPOSITORIES = 'delete-repositories';
    const COMMAND_REPOSITORY_URIS = 'repository-uris';

    protected $signature = 'larasurf:cloud-images
                            {--environment= : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'create-repositories\', \'delete-repositories\', or \'repository-uris\'}';

    protected $description = 'Manage images and image repositories in cloud environments';

    protected array $commands = [
        self::COMMAND_CREATE_REPOSITORIES => 'handleCreateRepositories',
        self::COMMAND_DELETE_REPOSITORIES => 'handleDeleteRepositories',
        self::COMMAND_REPOSITORY_URIS => 'handleRepositoryUris',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    public function handleCreateRepositories()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $aws_region = $this->choice('In which region would you like to create the repositories?', Cloud::AWS_REGIONS, 0);

        $ecr = $this->awsEcr($env, $aws_region);

        $uri_application = $ecr->createRepository($this->repositoryName($env, self::REPOSITORY_TYPE_APPLICATION));
        $uri_webserver = $ecr->createRepository($this->repositoryName($env, self::REPOSITORY_TYPE_WEBSERVER));

        $this->info('Repositories created successfully');
        $this->newLine();
        $this->info('Application image repository URI:');
        $this->getOutput()->writeln($uri_application);
        $this->newLine();
        $this->info('Webserver image repository URI:');
        $this->getOutput()->writeln($uri_webserver);

        $this->newLine();
        $this->info('Updating LaraSurf configuration...');

        static::config()->set("environments.$env.aws-region", $aws_region);

        if (!static::config()->write()) {
            $this->error('Failed to update LaraSurf configuration');

            return 1;
        }

        $this->info('Updated LaraSurf configuration successfully');

        return 0;
    }

    public function handleDeleteRepositories()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $aws_region = static::config()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error('Environment AWS region not found in configuration file');

            return 1;
        }

        $ecr = $this->awsEcr($env);

        $ecr->deleteRepository($this->repositoryName($env, self::REPOSITORY_TYPE_APPLICATION));
        $ecr->deleteRepository($this->repositoryName($env, self::REPOSITORY_TYPE_WEBSERVER));

        $this->info('Successfully deleted both the application and webserver image repositories');

        static::config()->set("environments.$env", null);

        if(!static::config()->write()) {
            $this->error('Failed to update LaraSurf configuration');

            return 1;
        }

        $this->info('Updated LaraSurf configuration successfully');

        return 0;
    }

    public function handleRepositoryUris()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $aws_region = static::config()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error('Environment AWS region not found in configuration file');

            return 1;
        }

        $ecr = $this->awsEcr($env);

        $application_uri = $ecr->repositoryUri($this->repositoryName($env, self::REPOSITORY_TYPE_APPLICATION));
        $webserver_uri = $ecr->repositoryUri($this->repositoryName($env, self::REPOSITORY_TYPE_WEBSERVER));

        if (!$application_uri || !$webserver_uri) {
            $this->error("Application and/or webserver repositories do not exist for the '$env' environment");

            return 1;
        }

        $this->info('Application image repository URI:');
        $this->getOutput()->writeln($application_uri);
        $this->newLine();
        $this->info('Websever image repository URI:');
        $this->getOutput()->writeln($webserver_uri);

        return 0;
    }

    protected function repositoryName(string $environment, string $type): string
    {
        return static::config()->get('project-name') . '-' . static::config()->get('project-id') . "-$environment/$type";
    }
}
