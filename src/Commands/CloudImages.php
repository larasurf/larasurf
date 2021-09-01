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

    const COMMAND_CREATE_REPOSITORIES = 'create-repositories';
    const COMMAND_DELETE_REPOSITORIES = 'delete-repositories';

    protected $signature = 'larasurf:cloud-images
                            {--environment= : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'create-repositories\', or \'delete-repositories\'}';

    protected $description = 'Manage images and image repositories in cloud environments';

    protected array $commands = [
        self::COMMAND_CREATE_REPOSITORIES => 'handleCreateRepositories',
        self::COMMAND_DELETE_REPOSITORIES => 'handleDeleteRepositories',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    public function handleCreateRepository()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $aws_region = $this->choice('Which region would you like to create the repository in?', Cloud::AWS_REGIONS, 0);

        $ecr = $this->awsEcr($env, $aws_region);

        $uri_application = $ecr->createRepository($this->repositoryName($env, 'application'));
        $uri_webserver = $ecr->createRepository($this->repositoryName($env, 'webserver'));

        $this->getOutput()->writeln("<info>Application repository created successfully with URI:</info> $uri_application");
        $this->getOutput()->writeln("<info>Webserver repository created successfully with URI:</info> $uri_webserver");

        $this->info('Updating LaraSurf configuration...');

        static::config()->set("environments.$env.aws-region", $aws_region);

        if (!static::config()->write()) {
            $this->error('Failed to update LaraSurf configuration');

            return 1;
        }

        $this->info('Updated configuration file successfully');

        return 0;
    }

    public function handleDeleteRepository()
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

        $ecr->deleteRepository($this->repositoryName($env, 'application'));
        $ecr->deleteRepository($this->repositoryName($env, 'webserver'));

        return 0;
    }

    protected function repositoryName(string $environment, string $type): string
    {
        return static::config()->get('project-name') . '-' . static::config()->get('project-id') . "-$environment/$type";
    }
}
