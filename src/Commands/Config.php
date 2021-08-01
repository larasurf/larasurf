<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Config extends Command
{
    use InteractsWithLaraSurfConfig;
    use HasSubCommand;

    const COMMAND_GET = 'get';
    const COMMAND_SET = 'set';

    const VALID_KEYS = [
        'aws-profile',
    ];

    protected $signature = 'larasurf:config {subcommand} {key} {value?}';

    protected $description = 'Configure LaraSurf';

    protected $commands = [
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_SET => 'handleSet',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return;
        }

        $key = $this->argument('key');

        if (!in_array($key, self::VALID_KEYS)) {
            $this->error('Invalid config key specified');

            return;
        }

        $this->runSubCommand();
    }

    protected function handleGet()
    {
        $key = $this->argument('key');

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        if ($config['schema-version'] === 1) {
            if (isset($config[$key])) {
                $this->info("$key: {$config[$key]}");
            } else {
                $this->error("Key '$key' not found in larasurf.json");
            }
        }
    }

    protected function handleSet()
    {
        $value = $this->argument('value');

        if (!$value) {
            $this->error('A config value must be specified');

            return;
        }

        $key = $this->argument('key');

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        if ($config['schema-version'] === 1) {
            $config[$key] = $value;
        }

        $this->writeLaraSurfConfig($config);
    }
}
