<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\AccessKey;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudUsersTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreate()
    {
        $this->createCircleCIApiKey(Str::random());
        $this->createValidLaraSurfConfig('local-stage-production');

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([]);
        $circleci->shouldReceive('createEnvironmentVariable')->times(2)->andReturn();

        $iam = $this->mockLaraSurfIamClient();
        $iam->shouldReceive('userExists')->andReturn(false);
        $iam->shouldReceive('createUser')->andReturn();
        $iam->shouldReceive('attachUserPolicy')->andReturn();
        $iam->shouldReceive('createAccessKey')->andReturn((new AccessKey())
            ->setId(Str::random())
            ->setSecret(Str::random())
        );

        $iam_user = "{$this->project_name}-{$this->project_id}-circleci";

        $this->artisan('larasurf:cloud-users create --user circleci')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput('Checking if cloud user exists...')
            ->expectsOutput("Creating user '$iam_user'...")
            ->expectsOutput('Assigning permissions...')
            ->expectsOutput('Creating access keys...')
            ->expectsOutput('Updating CircleCI environment variables...')
            ->expectsOutput("Set CircleCI environment variable 'AWS_ACCESS_KEY_ID' successfully")
            ->expectsOutput("Set CircleCI environment variable 'AWS_SECRET_ACCESS_KEY' successfully")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateEnvironmentVariablesExist()
    {
        $this->createCircleCIApiKey(Str::random());
        $this->createValidLaraSurfConfig('local-stage-production');

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([
            'AWS_ACCESS_KEY_ID' => Str::random(),
            'AWS_SECRET_ACCESS_KEY' => Str::random(),
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->times(2)->andReturn();
        $circleci->shouldReceive('createEnvironmentVariable')->times(2)->andReturn();

        $iam = $this->mockLaraSurfIamClient();
        $iam->shouldReceive('userExists')->andReturn(false);
        $iam->shouldReceive('createUser')->andReturn();
        $iam->shouldReceive('attachUserPolicy')->andReturn();
        $iam->shouldReceive('createAccessKey')->andReturn((new AccessKey())
            ->setId(Str::random())
            ->setSecret(Str::random())
        );

        $iam_user = "{$this->project_name}-{$this->project_id}-circleci";

        $this->artisan('larasurf:cloud-users create --user circleci')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput("CircleCI environment variable 'AWS_ACCESS_KEY_ID' exists!")
            ->expectsOutput("CircleCI environment variable 'AWS_SECRET_ACCESS_KEY' exists!")
            ->expectsConfirmation('Would you like to delete these CircleCI environment variables and proceed?', 'yes')
            ->expectsOutput('Deleting CircleCI environment variables...')
            ->expectsOutput('Checking if cloud user exists...')
            ->expectsOutput("Creating user '$iam_user'...")
            ->expectsOutput('Assigning permissions...')
            ->expectsOutput('Creating access keys...')
            ->expectsOutput('Updating CircleCI environment variables...')
            ->expectsOutput("Set CircleCI environment variable 'AWS_ACCESS_KEY_ID' successfully")
            ->expectsOutput("Set CircleCI environment variable 'AWS_SECRET_ACCESS_KEY' successfully")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateUserExists()
    {
        $this->createCircleCIApiKey(Str::random());
        $this->createValidLaraSurfConfig('local-stage-production');

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([]);

        $iam = $this->mockLaraSurfIamClient();
        $iam->shouldReceive('userExists')->andReturn(true);

        $iam_user = "{$this->project_name}-{$this->project_id}-circleci";

        $this->artisan('larasurf:cloud-users create --user circleci')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput('Checking if cloud user exists...')
            ->expectsOutput("Cloud user '$iam_user' already exists")
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDelete()
    {
        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $this->createCircleCIApiKey(Str::random());
        $this->createValidLaraSurfConfig('local-stage-production');

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([
            'AWS_ACCESS_KEY_ID' => Str::random(),
            'AWS_SECRET_ACCESS_KEY' => Str::random(),
        ]);
        $circleci->shouldReceive('deleteEnvironmentVariable')->times(2)->andReturn();

        $iam = $this->mockLaraSurfIamClient();
        $iam->shouldReceive('userExists')->andReturn(true);
        $iam->shouldReceive('detachUserPolicy')->andReturn();
        $iam->shouldReceive('listAccessKeys')->andReturn([
            Str::random(),
            Str::random(),
        ]);
        $iam->shouldReceive('deleteAccessKey')->times(2)->andReturn();
        $iam->shouldReceive('deleteUser')->andReturn();

        $iam_user = "{$this->project_name}-{$this->project_id}-circleci";

        $this->artisan('larasurf:cloud-users delete --user circleci')
            ->expectsOutput('Checking if cloud user exists...')
            ->expectsOutput('Detaching user policies...')
            ->expectsOutput('Deleting access keys...')
            ->expectsOutput("Deleting user '$iam_user'...")
            ->expectsOutput('Deleted cloud user successfully')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput("CircleCI environment variable 'AWS_ACCESS_KEY_ID' exists!")
            ->expectsOutput("CircleCI environment variable 'AWS_SECRET_ACCESS_KEY' exists!")
            ->expectsConfirmation('Would you like to delete these CircleCI environment variables and proceed?', 'yes')
            ->expectsOutput('Deleting CircleCI environment variables...')
            ->expectsOutput('Deleted CircleCI environment variables successfully')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDeleteDontDeleteEnvironmentVariables()
    {
        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $this->createCircleCIApiKey(Str::random());
        $this->createValidLaraSurfConfig('local-stage-production');

        $circleci = $this->mockCircleCI();
        $circleci->shouldReceive('projectExists')->andReturn(true);
        $circleci->shouldReceive('listEnvironmentVariables')->andReturn([
            'AWS_ACCESS_KEY_ID' => Str::random(),
            'AWS_SECRET_ACCESS_KEY' => Str::random(),
        ]);

        $iam = $this->mockLaraSurfIamClient();
        $iam->shouldReceive('userExists')->andReturn(true);
        $iam->shouldReceive('detachUserPolicy')->andReturn();
        $iam->shouldReceive('listAccessKeys')->andReturn([
            Str::random(),
            Str::random(),
        ]);
        $iam->shouldReceive('deleteAccessKey')->times(2)->andReturn();
        $iam->shouldReceive('deleteUser')->andReturn();

        $iam_user = "{$this->project_name}-{$this->project_id}-circleci";

        $this->artisan('larasurf:cloud-users delete --user circleci')
            ->expectsOutput('Checking if cloud user exists...')
            ->expectsOutput('Detaching user policies...')
            ->expectsOutput('Deleting access keys...')
            ->expectsOutput("Deleting user '$iam_user'...")
            ->expectsOutput('Deleted cloud user successfully')
            ->expectsOutput('Checking CircleCI project is enabled...')
            ->expectsOutput('Checking CircleCI environment variables...')
            ->expectsOutput("CircleCI environment variable 'AWS_ACCESS_KEY_ID' exists!")
            ->expectsOutput("CircleCI environment variable 'AWS_SECRET_ACCESS_KEY' exists!")
            ->expectsConfirmation('Would you like to delete these CircleCI environment variables and proceed?', 'no')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDeleteUserDoesntExist()
    {
        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $this->createCircleCIApiKey(Str::random());
        $this->createValidLaraSurfConfig('local-stage-production');

        $iam = $this->mockLaraSurfIamClient();
        $iam->shouldReceive('userExists')->andReturn(false);

        $iam_user = "{$this->project_name}-{$this->project_id}-circleci";

        $this->artisan('larasurf:cloud-users delete --user circleci')
            ->expectsOutput('Checking if cloud user exists...')
            ->expectsOutput("Cloud user '$iam_user' does not exist")
            ->assertExitCode(1);
    }
}
