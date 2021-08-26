<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;

class Config extends Command
{
    use HasSubCommand;

    const COMMAND_GET = 'get';
    const COMMAND_SET = 'set';

    protected $signature = 'larasurf:config {subcommand} {key} {value?}';

    protected $description = 'Configure LaraSurf';

    protected array $commands = [
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_SET => 'handleSet',
    ];

    protected string $config_file = 'larasurf.json';

    protected \LaraSurf\LaraSurf\Config $config;

    public function __construct()
    {
        $this->config = new \LaraSurf\LaraSurf\Config($this->config_file);

        parent::__construct();
    }

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    protected function handleGet()
    {
        $key = $this->argument('key');

        $value = $this->config->get($key);

        if ($value !== null) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $this->line($value);
        } else {
            $this->error("Key '$key' not found in larasurf.json");

            return 1;
        }

        return 0;
    }

    protected function handleSet()
    {
        $value = $this->argument('value');

        if (!$value) {
            $this->error('A config value must be specified');

            return 1;
        }

        $key = $this->argument('key');

        $this->config->set($key, $value);

        if (!$this->config->write()) {
            $this->error("Failed to write to file '{$this->config_file}'");

            return 1;
        }

        $this->info("File '{$this->config_file}' updated successfully");

        return 0;
    }
}
