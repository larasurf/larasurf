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

    const COMMAND_CREATE_REPOSITORY = 'create-repository';
    const COMMAND_DELETE_REPOSITORY = 'delete-repository';

    protected $signature = 'larasurf:cloud-images
                            {--environment= : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'create-repository\', or \'delete-repository\'}';

    protected $description = 'Manage images and image registries in cloud environments';

    protected array $commands = [
        self::COMMAND_CREATE_REPOSITORY => 'handleCreateRepository',
        self::COMMAND_DELETE_REPOSITORY => 'handleDeleteRepository',
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

        $uri = $ecr->createRepository($this->repositoryName($env));

        $this->getOutput()->writeln("<info>Repository created successfully with URI:</info> $uri");

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

        $ecr->deleteRepository($this->repositoryName($env));

        return 0;
    }

    protected function repositoryName(string $environment): string
    {
        return static::config()->get('project-name') . '-' . static::config()->get('project-id') . '-' . $environment;
    }
}
