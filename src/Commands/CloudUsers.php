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

    const COMMAND_CREATE = 'create';
    const COMMAND_DELETE = 'delete';

    protected $signature = 'larasurf:cloud-users
                            {--user= : Specify the cloud user}
                            {subcommand : The subcommand to run: \'create\' or \'delete\'}';

    protected $description = 'Manage users in the cloud';

    protected array $commands = [
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_DELETE => 'handleDelete',
    ];

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

        $circleci_project = $this->gitOriginUrl();

        $circleci = static::circleCI($circleci_api_key, $circleci_project);

        $iam_user = $this->iamUserName($user);

        $iam = static::awsIam();

        if ($iam->userExists($iam_user)) {
            $this->error("IAM user '$iam_user' already exists");

            return 1;
        }

        $this->info("Creating user '$iam_user'...");

        $iam->createUser($iam_user);

        $this->info("Creating access keys...");

        $access_keys = $iam->createAccessKeys($iam_user);

        $circleci->createEnvironmentVariable('AWS_ACCESS_KEY_ID', $access_keys->getId());
        $circleci->createEnvironmentVariable('AWS_SECRET_ACCESS_KEY', $access_keys->getSecret());

        return 0;
    }

    protected function handleDelete()
    {
        $user = $this->userOption();

        if (!$user) {
            return 1;
        }

        $iam_user = $this->iamUserName($user);

        $iam = static::awsIam();

        if (!$iam->userExists($iam_user)) {
            $this->warn("IAM user '$iam_user' does not exist");

            return 0;
        }

        $iam->deleteUser($user);

        return 0;
    }

    protected function userOption(): string|false
    {
        $user = $this->option('user');

        if (!in_array($user, Cloud::USERS)) {
            $this->error('Invalid cloud user specified');

            return false;
        }

        return $user;
    }

    protected function iamUserName(string $user): string
    {
        $name = static::larasurfConfig()->get('project-name');
        $id = static::larasurfConfig()->get('project-id');

        return "$name-$id-$user";
    }
}
