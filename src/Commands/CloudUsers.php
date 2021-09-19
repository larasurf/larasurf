<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithCircleCI;
use LaraSurf\LaraSurf\Constants\Cloud;

class CloudUsers extends Command
{
    use HasSubCommands;
    use InteractsWithAws;
    use InteractsWithCircleCI;

    /**
     * The policy ARN for administrator access.
     */
    const IAM_POLICY_ARN_ADMIN_ACCESS = 'arn:aws:iam::aws:policy/AdministratorAccess';

    /**
     * The available subcommands to run.
     */
    const COMMAND_CREATE = 'create';
    const COMMAND_DELETE = 'delete';

    /**
     * @var string
     */
    protected $signature = 'larasurf:cloud-users
                            {--user= : Specify the cloud user}
                            {subcommand : The subcommand to run: \'create\' or \'delete\'}';

    /**
     * @var string
     */
    protected $description = 'Manage users in the cloud';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_DELETE => 'handleDelete',
    ];

    /**
     * Create a cloud user. Only CircleCI is supported.
     *
     * @return int
     * @throws \LaraSurf\LaraSurf\Exceptions\CircleCI\RequestFailedException
     */
    protected function handleCreate()
    {
        $user = $this->userOption();

        if (!$user) {
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

        $circleci_existing_vars = $this->circleCIExistingEnvironmentVariablesAskDelete($circleci, [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
        ]);

        if ($circleci_existing_vars === false) {
            return 1;
        }

        if ($circleci_existing_vars) {
            $this->line('Deleting CircleCI environment variables...');

            foreach ($circleci_existing_vars as $name) {
                $circleci->deleteEnvironmentVariable($name);
            }
        }

        $iam_user = $this->iamUserName($user);

        $iam = static::awsIam();

        $this->line('Checking if cloud user exists...');

        if ($iam->userExists($iam_user)) {
            $this->error("Cloud user '$iam_user' already exists");

            return 1;
        }

        $this->line("Creating user '$iam_user'...");

        $iam->createUser($iam_user);

        $this->line('Assigning permissions...');

        // todo: create policy, assign that instead of admin access

        $iam->attachUserPolicy($iam_user, self::IAM_POLICY_ARN_ADMIN_ACCESS);

        $this->line('Creating access keys...');

        $access_keys = $iam->createAccessKey($iam_user);

        $this->line('Updating CircleCI environment variables...');

        foreach ([
            'AWS_ACCESS_KEY_ID' => $access_keys->getId(),
            'AWS_SECRET_ACCESS_KEY' => $access_keys->getSecret(),
                 ] as $name => $value) {
            $circleci->createEnvironmentVariable($name, $value);

            $this->info("Set CircleCI environment variable '$name' successfully");
        }

        return 0;
    }

    /**
     * Delete a cloud user.
     *
     * @return int
     */
    protected function handleDelete()
    {
        $user = $this->userOption();

        if (!$user) {
            return 1;
        }

        $iam_user = $this->iamUserName($user);

        $iam = static::awsIam();

        $this->line('Checking if cloud user exists...');

        if (!$iam->userExists($iam_user)) {
            $this->warn("Cloud user '$iam_user' does not exist");

            return 1;
        }

        $this->line('Detaching user policies...');

        $iam->detachUserPolicy($iam_user, self::IAM_POLICY_ARN_ADMIN_ACCESS);

        $this->line('Deleting access keys...');

        $access_keys = $iam->listAccessKeys($iam_user);

        foreach ($access_keys as $access_key_id) {
            $iam->deleteAccessKey($iam_user, $access_key_id);
        }

        $this->line("Deleting user '$iam_user'...");

        $iam->deleteUser($iam_user);

        $this->info('Deleted cloud user successfully');

        $this->maybeDeleteCircleCIEnvironmentVariables([
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
        ]);

        return 0;
    }

    /**
     * @return string|false
     */
    protected function userOption(): string|false
    {
        $user = $this->option('user');

        if (!in_array($user, Cloud::USERS)) {
            $this->error('Invalid cloud user specified');

            return false;
        }

        return $user;
    }

    /**
     * @param string $user
     * @return string
     */
    protected function iamUserName(string $user): string
    {
        $name = static::larasurfConfig()->get('project-name');
        $id = static::larasurfConfig()->get('project-id');

        return "$name-$id-$user";
    }
}
