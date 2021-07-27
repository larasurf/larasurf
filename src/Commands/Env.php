<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentArgument;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Env extends Command
{
    use InteractsWithLaraSurfConfig;
    use HasEnvironmentArgument;
    use HasSubCommand;

    const COMMAND_INIT = 'init';
    const COMMAND_GET = 'get';
    const COMMAND_PUT = 'put';
    const COMMAND_DELETE = 'delete';
    const COMMAND_LIST = 'list';

    protected $signature = 'larasurf:env {subcommand} {environment} {--region=} {--key=} {--value=}';

    protected $description = 'Manipulate environment variables for an upstream environment';

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
        if (!$this->validateEnvironmentArgument()) {
            return;
        }

        if (!$this->validateSubCommandArgument()) {
            return;
        }

        $this->runSubCommand();
    }

    protected function handleInit()
    {
        $aws_region = $this->option('region');

        if (!$aws_region) {
            $this->error('AWS region must be specified with the --region option');

            return;
        }

        if (!in_array($aws_region, $this->valid_aws_regions)) {
            $this->error('Invalid AWS region specified');

            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            $this->error('Failed to load larasurf.json');

            return;
        }

        if ($config['schema-version'] === 1) {
            $this->info("ToDo: handle init");
        }
    }

    protected function handleGet()
    {
        $this->info('ToDo: handle get');
    }

    protected function handlePut()
    {
        $this->info('ToDo: handle put');
    }

    protected function handleDelete()
    {
        $this->info('ToDo: handle delete');
    }

    protected function handleList()
    {
        $this->info('ToDo: handle list');
    }
}
