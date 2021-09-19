<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Config extends Command
{
    use HasSubCommands;
    use InteractsWithLaraSurfConfig;

    /**
     * The available subcommands to run.
     */
    const COMMAND_GET = 'get';
    const COMMAND_SET = 'set';

    /**
     * @var string
     */
    protected $signature = 'larasurf:config
                            {subcommand : The subcommand to run: \'get\' or \'set\'}
                            {key : The config key, supports dot notation}
                            {value? : The config value, required with \'set\'}';

    /**
     * @var string
     */
    protected $description = 'Read and update the LaraSurf configuration file';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_SET => 'handleSet',
    ];

    /**
     * Get a value from the LaraSurf configuration file using dot notation.
     *
     * @return int
     */
    protected function handleGet()
    {
        $key = $this->argument('key');

        $value = static::larasurfConfig()->get($key);

        if ($value === null) {
            $this->error("Key '$key' not found in '" . static::laraSurfConfigFilePath() . "'");

            return 1;
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        $this->line($value);

        return 0;
    }

    /**
     * Set a value in the LaraSurf configuration file using dot notation.
     *
     * @return int
     * @throws \LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigKeyException
     * @throws \LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigValueException
     */
    protected function handleSet()
    {
        $value = $this->argument('value');

        if (!$value) {
            $this->error('A config value must be specified');

            return 1;
        }

        $key = $this->argument('key');

        static::larasurfConfig()->set($key, $value);

        if (!static::larasurfConfig()->write()) {
            $this->error("Failed to write to file '" . static::laraSurfConfigFilePath() . "'");

            return 1;
        }

        $this->info("File '" . static::laraSurfConfigFilePath() ."' updated successfully");

        return 0;
    }
}
