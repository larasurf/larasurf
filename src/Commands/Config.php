<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\HasValidEnvironments;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Config extends Command
{
    use InteractsWithLaraSurfConfig;
    use HasValidEnvironments;
    use HasSubCommand;

    const COMMAND_GET = 'get';
    const COMMAND_SET = 'set';

    const VALID_KEYS = [
        'aws-profile',
        'cloud-environments.stage.domain',
        'cloud-environments.production.domain',
        'cloud-environments.stage.aws-certificate-arn',
        'cloud-environments.production.aws-certificate-arn',
        'cloud-environments.stage.stack-deployed',
        'cloud-environments.production.stack-deployed',
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
            return 1;
        }

        $key = $this->argument('key');

        if (!in_array($key, self::VALID_KEYS)) {
            $this->error('Invalid config key specified');

            return 1;
        }

        return $this->runSubCommand();
    }

    protected function handleGet()
    {
        $key = $this->argument('key');

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        if ($config['schema-version'] === 1) {
            if (!$this->validateUpstreamEnvironment($config, $key)) {
                return 1;
            }

            $value = Arr::get($config, $key);

            if ($value !== null) {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                $this->info("$key: $value");
            } else {
                $this->error("Key '$key' not found in larasurf.json");

                return 1;
            }
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

        if (strtolower($value) === 'true') {
            $value = true;
        } else if (strtolower($value) === 'false') {
            $value = false;
        }

        $key = $this->argument('key');

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        if ($config['schema-version'] === 1) {
            if (!$this->validateUpstreamEnvironment($config, $key)) {
                return 1;
            }

            Arr::set($config, $key, $value);
        }

        return $this->writeLaraSurfConfig($config) ? 0 : 1;
    }

    protected function validateUpstreamEnvironment($config, $key)
    {
        if (str_starts_with($key, 'cloud-environments.')) {
            $environment = explode('.', $key)[1] ?? '';

            return $this->validateEnvironmentExistsInConfig($config, $environment);
        }

        return false;
    }
}
