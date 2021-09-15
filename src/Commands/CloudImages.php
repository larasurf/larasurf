<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\CircleCI\Client;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithCircleCI;
use LaraSurf\LaraSurf\Constants\Cloud;

class CloudImages extends Command
{
    use HasSubCommands;
    use HasEnvironmentOption;
    use InteractsWithAws;
    use InteractsWithCircleCI;

    const REPOSITORY_TYPE_APPLICATION = 'application';
    const REPOSITORY_TYPE_WEBSERVER = 'webserver';

    const COMMAND_CREATE_REPOS = 'create-repos';
    const COMMAND_DELETE_REPOS = 'delete-repos';

    protected $signature = 'larasurf:cloud-images
                            {--environment= : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'create-repos\' or \'delete-repos\'}';

    protected $description = 'Manage images and image repositories in cloud environments';

    protected array $commands = [
        self::COMMAND_CREATE_REPOS => 'handleCreateRepo',
        self::COMMAND_DELETE_REPOS => 'handleDeleteRepo',
    ];

    public function handleCreateRepo()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        if (!$this->gitIsOnBranch('develop')) {
            $this->error('The develop branch should be checked out before running this command');

            return 1;
        }

        $circleci_api_key = static::circleCIApiKey();

        if (!$circleci_api_key) {
            $this->error('Set a CircleCI API key first');

            return 1;
        }

        $circleci_project = $this->gitOriginProjectName();

        if (!$circleci_project) {
            return 1;
        }

        $circleci = static::circleCI($circleci_api_key, $circleci_project);

        $this->line('Checking CircleCI project is enabled...');

        if (!$circleci->projectExists()) {
            $this->error('CircleCI project has not yet been enabled through the web console');

            return 1;
        }

        $this->line('Checking CircleCI environment variables...');

        $suffix = strtoupper($env);

        $circleci_existing_vars = $this->circleCIExistingEnvironmentVariablesAskDelete($circleci, [
            'AWS_REGION_' . $suffix,
            'AWS_ECR_URL_PREFIX_' . $suffix,
        ]);

        if ($circleci_existing_vars === false) {
            return 1;
        }

        $aws_region = $this->choice('In which region will this project be deployed?', Cloud::AWS_REGIONS, 0);

        if ($circleci_existing_vars) {
            $this->line('Deleting CircleCI environment variables...');

            foreach ($circleci_existing_vars as $name) {
                $circleci->deleteEnvironmentVariable($name);
            }
        }

        $ecr = $this->awsEcr($env, $aws_region);

        $this->line('Creating image repositories...');

        $uri_application = $ecr->createRepository($this->awsEcrRepositoryName($env, self::REPOSITORY_TYPE_APPLICATION));
        $ecr->createRepository($this->awsEcrRepositoryName($env, self::REPOSITORY_TYPE_WEBSERVER));

        $this->info('Repositories created successfully');
        $this->line('Updating LaraSurf configuration...');

        static::larasurfConfig()->set("environments.$env.aws-region", $aws_region);

        if (!static::larasurfConfig()->write()) {
            $this->error('Failed to update LaraSurf configuration');

            return 1;
        }

        $this->info('Updated LaraSurf configuration successfully');

        $this->line('Updating CircleCI environment variables...');

        foreach ([
            'AWS_REGION_' . $suffix => $aws_region,
            'AWS_ECR_URL_PREFIX_' . $suffix => Str::replace("/$env/application", '', $uri_application),
                 ] as $name => $value) {
            $circleci->createEnvironmentVariable($name, $value);

            $this->info("Set CircleCI environment variable '$name' successfully");
        }

        return 0;
    }

    public function handleDeleteRepo()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        if (!$this->gitIsOnBranch('develop')) {
            $this->error('The develop branch should be checked out before running this command');

            return 1;
        }

        $aws_region = static::larasurfConfig()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error('Environment AWS region not found in configuration file');

            return 1;
        }

        $suffix = strtoupper($env);

        $this->maybeDeleteCircleCIEnvironmentVariables([
            'AWS_REGION_' . $suffix,
            'AWS_ECR_URL_PREFIX_' . $suffix,
        ]);

        $cloudformation = $this->awsCloudFormation($env, $aws_region);

        if ($cloudformation->stackStatus()) {
            $this->error("Stack exists for '$env' environment; delete that first");

            return 1;
        }

        $ecr = $this->awsEcr($env);

        $ecr->deleteRepository($this->awsEcrRepositoryName($env, self::REPOSITORY_TYPE_APPLICATION));
        $ecr->deleteRepository($this->awsEcrRepositoryName($env, self::REPOSITORY_TYPE_WEBSERVER));

        $this->info('Deleted both application and webserver image repositories successfully');

        static::larasurfConfig()->set("environments.$env", null);

        if(!static::larasurfConfig()->write()) {
            $this->error('Failed to update LaraSurf configuration');

            return 1;
        }

        $this->info('Updated LaraSurf configuration successfully');

        return 0;
    }
}
