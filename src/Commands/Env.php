<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Env extends Command
{
    use InteractsWithLaraSurfConfig;
    use HasSubCommand;

    const COMMAND_INIT = 'init';
    const COMMAND_GET = 'get';
    const COMMAND_PUT = 'put';
    const COMMAND_DELETE = 'delete';
    const COMMAND_LIST = 'list';

    protected $signature = 'larasurf:env {--env=} {command} {arg1?} {arg2?}';

    protected $description = 'List all environment variables or get, put, or delete an environment variable in an upstream environment';

    protected $commands = [
        self::COMMAND_INIT => 'handleInit',
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_PUT => 'handlePut',
        self::COMMAND_DELETE => 'handleDelete',
        self::COMMAND_LIST => 'handleList',
    ];

    protected $valid_aws_regions = [
        'us-east-1',
    ];

    public function handle()
    {
        if (!$this->validateEnvOption()) {
            return;
        }

        if (!$this->validateCommandArgument()) {
            return;
        }

        $this->runCommand();
    }

    protected function handleInit()
    {
        $environment = $this->argument('arg1');

        if (!in_array($environment, $this->valid_environments)) {
            $this->error('Invalid environment specified');

            return;
        }

        $aws_region = $this->argument('arg2');

        if (!in_array($aws_region, $this->valid_aws_regions)) {
            $this->error('Invalid AWS region specified');

            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        if ($config['schema-version'] === 1) {
            $this->info("ToDo: handle init");
        }
    }

    protected function handleGet()
    {
        $this->info("ToDo: handle get");
    }

    protected function handlePut()
    {
        $this->info("ToDo: handle put");
    }

    protected function handleDelete()
    {
        $this->info("ToDo: handle delete");
    }

    protected function handleList()
    {
        $this->info("ToDo: handle list");
    }
}
