<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudImagesTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateRepos()
    {
        $this->createGitHead('develop');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([]);
        $circleci->shouldReceive('createEnvironmentVariable')->andReturn();
        $circleci->shouldReceive('createEnvironmentVariable')->andReturn();

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('createRepository')->andReturn($this->faker->url);
        $ecr->shouldReceive('createRepository')->andReturn($this->faker->url);

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

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateReposEnvironmentVariablesExist()
    {
        $this->createGitHead('develop');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([
            'AWS_REGION_PRODUCTION' => 'us-east-1',
            'AWS_ECR_URL_PREFIX_PRODUCTION' => $this->faker->url,
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->andReturn();
        $circleci->shouldReceive('deleteEnvironmentVariable')->andReturn();
        $circleci->shouldReceive('createEnvironmentVariable')->andReturn();
        $circleci->shouldReceive('createEnvironmentVariable')->andReturn();

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('createRepository')->andReturn($this->faker->url);
        $ecr->shouldReceive('createRepository')->andReturn($this->faker->url);

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

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateReposProjectNotEnabled()
    {
        $this->createGitHead('develop');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(false);

        $this->artisan('larasurf:cloud-images create-repos --environment production')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('CircleCI project has not yet been enabled through the web console')
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateReposNotOnDevelop()
    {
        $this->createGitHead('stage');

        $this->artisan('larasurf:cloud-images create-repos --environment production')
            ->expectsOutput('The develop branch should be checked out before running this command')
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDeleteRepo()
    {
        $this->createGitHead('develop');

        $this->createValidLaraSurfConfig('local-stage-production');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([
            'AWS_REGION_PRODUCTION' => 'us-east-1',
            'AWS_ECR_URL_PREFIX_PRODUCTION' => $this->faker->url,
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->andReturn();
        $circleci->shouldReceive('deleteEnvironmentVariable')->andReturn();

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);

        $ecr = $this->mockAwsEcrClient();
        $ecr->shouldReceive('deleteRepository')->andReturn();
        $ecr->shouldReceive('deleteRepository')->andReturn();

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

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDeleteRepoStackExists()
    {
        $this->createGitHead('develop');

        $this->createValidLaraSurfConfig('local-stage-production');

        $this->createCircleCIApiKey(Str::random());

        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([
            'AWS_REGION_PRODUCTION' => 'us-east-1',
            'AWS_ECR_URL_PREFIX_PRODUCTION' => $this->faker->url,
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->andReturn();
        $circleci->shouldReceive('deleteEnvironmentVariable')->andReturn();

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn(true);

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
