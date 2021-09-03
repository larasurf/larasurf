<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Constants\Cloud;

class CloudUsers extends Command
{
    use HasSubCommands;
    use InteractsWithAws;

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

        // todo

        return 0;
    }

    protected function handleDelete()
    {
        $user = $this->userOption();

        if (!$user) {
            return 1;
        }
        
        // todo

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
}
