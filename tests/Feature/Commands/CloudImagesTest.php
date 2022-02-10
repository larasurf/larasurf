<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\CircleCI\Client;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudImagesTest extends TestCase
{
    public function testCreateRepos()
    {
        $this->createGitHead('develop');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->once()->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->once()->andReturn([]);
        $circleci->shouldReceive('createEnvironmentVariable')->once()->andReturn();
        $circleci->shouldReceive('createEnvironmentVariable')->once()->andReturn();

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('createRepository')->once()->andReturn($this->faker->url);
        $ecr->shouldReceive('createRepository')->once()->andReturn($this->faker->url);

        $this->artisan('larasurf:cloud-images create-repos --environment production')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsQuestion('In which region will this project be deployed?', 'us-east-1')
            ->expectsOutput('Creating image repositories...')
            ->expectsOutput('Repositories created successfully')
            ->expectsOutput('Updating LaraSurf configuration...')
            ->expectsOutput('Updated LaraSurf configuration successfully')
            ->expectsOutput('Updating CircleCI environment variables...')
            ->expectsOutput("Set CircleCI environment variable 'AWS_REGION_PRODUCTION' successfully")
            ->expectsOutput("Set CircleCI environment variable 'AWS_ECR_URL_PREFIX_PRODUCTION' successfully")
            ->assertExitCode(0);
    }

    public function testCreateReposEnvironmentVariablesExist()
    {
        global $debug_var;
        $debug_var = true;

        $this->createGitHead('develop');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->once()->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->once()->andReturn([
            'AWS_REGION_PRODUCTION' => 'us-east-1',
            'AWS_ECR_URL_PREFIX_PRODUCTION' => $this->faker->url,
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->twice()->andReturn();
        $circleci->shouldReceive('createEnvironmentVariable')->twice()->andReturn();

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('createRepository')->once()->andReturn($this->faker->url);
        $ecr->shouldReceive('createRepository')->once()->andReturn($this->faker->url);

        $this->artisan('larasurf:cloud-images create-repos --environment production')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput("CircleCI environment variable 'AWS_REGION_PRODUCTION' exists!")
            ->expectsOutput("CircleCI environment variable 'AWS_ECR_URL_PREFIX_PRODUCTION' exists!")
            ->expectsQuestion('Would you like to delete these CircleCI environment variables and proceed?', true)
            ->expectsQuestion('In which region will this project be deployed?', 'us-east-1')
            ->expectsOutput('Deleting CircleCI environment variables...')
            ->expectsOutput('Creating image repositories...')
            ->expectsOutput('Repositories created successfully')
            ->expectsOutput('Updating LaraSurf configuration...')
            ->expectsOutput('Updated LaraSurf configuration successfully')
            ->expectsOutput('Updating CircleCI environment variables...')
            ->expectsOutput("Set CircleCI environment variable 'AWS_REGION_PRODUCTION' successfully")
            ->expectsOutput("Set CircleCI environment variable 'AWS_ECR_URL_PREFIX_PRODUCTION' successfully")
            ->assertExitCode(0);
    }

    public function testCreateReposProjectNotEnabled()
    {
        $this->createGitHead('develop');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->once()->andReturn(false);

        $this->artisan('larasurf:cloud-images create-repos --environment production')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('CircleCI project has not yet been enabled through the web console')
            ->assertExitCode(1);
    }

    public function testCreateReposNotOnDevelop()
    {
        $this->createGitHead('stage');

        $this->artisan('larasurf:cloud-images create-repos --environment production')
            ->expectsOutput('The develop branch should be checked out before running this command')
            ->assertExitCode(1);
    }

    public function testDeleteRepo()
    {
        $this->createGitHead('develop');

        $this->createValidLaraSurfConfig('local-stage-production');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->once()->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->once()->andReturn([
            'AWS_REGION_PRODUCTION' => 'us-east-1',
            'AWS_ECR_URL_PREFIX_PRODUCTION' => $this->faker->url,
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->twice()->andReturn();

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn(false);

        $ecr = $this->mockAwsEcrClient();
        $ecr->shouldReceive('deleteRepository')->twice()->andReturn();

        $this->artisan('larasurf:cloud-images delete-repos --environment production')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput("CircleCI environment variable 'AWS_REGION_PRODUCTION' exists!")
            ->expectsOutput("CircleCI environment variable 'AWS_ECR_URL_PREFIX_PRODUCTION' exists!")
            ->expectsQuestion('Would you like to delete these CircleCI environment variables and proceed?', true)
            ->expectsOutput('Deleting CircleCI environment variables...')
            ->expectsOutput('Deleted CircleCI environment variables successfully')
            ->expectsOutput('Deleted both application and webserver image repositories successfully')
            ->expectsOutput('Updated LaraSurf configuration successfully')
            ->assertExitCode(0);
    }

    public function testDeleteRepoStackExists()
    {
        $this->createGitHead('develop');

        $this->createValidLaraSurfConfig('local-stage-production');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->once()->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->once()->andReturn([
            'AWS_REGION_PRODUCTION' => 'us-east-1',
            'AWS_ECR_URL_PREFIX_PRODUCTION' => $this->faker->url,
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->twice()->andReturn();

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn(true);

        $this->artisan('larasurf:cloud-images delete-repos --environment production')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput("CircleCI environment variable 'AWS_REGION_PRODUCTION' exists!")
            ->expectsOutput("CircleCI environment variable 'AWS_ECR_URL_PREFIX_PRODUCTION' exists!")
            ->expectsQuestion('Would you like to delete these CircleCI environment variables and proceed?', true)
            ->expectsOutput('Deleting CircleCI environment variables...')
            ->expectsOutput('Deleted CircleCI environment variables successfully')
            ->assertExitCode(1);
    }
}
