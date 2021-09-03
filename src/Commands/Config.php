<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Config extends Command
{
    use HasSubCommands;
    use InteractsWithLaraSurfConfig;

    const COMMAND_GET = 'get';
    const COMMAND_SET = 'set';

    protected $signature = 'larasurf:config
                            {subcommand : The subcommand to run: \'get\' or \'set\'}
                            {key : The config key, supports dot notation}
                            {value? : The config value, required with \'set\'}';

    protected $description = 'Read and update the LaraSurf configuration file';

    protected array $commands = [
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_SET => 'handleSet',
    ];

    protected function handleGet()
    {
        $key = $this->argument('key');

        $value = static::config()->get($key);

        if ($value !== null) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $this->line($value);
        } else {
            $this->error("Key '$key' not found in '" . static::laraSurfConfigFilePath() . "'");

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

        static::config()->set($key, $value);

        if (!static::config()->write()) {
            $this->error("Failed to write to file '" . static::laraSurfConfigFilePath() . "'");

            return 1;
        }

        $this->info("File '" . static::laraSurfConfigFilePath() ."' updated successfully");

        return 0;
    }
}
