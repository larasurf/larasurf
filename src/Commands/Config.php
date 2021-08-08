<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    protected $signature = 'larasurf:config {subcommand} {key} {value?}';

    protected $description = 'Configure LaraSurf';

    protected $commands = [
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_SET => 'handleSet',
    ];

    protected $rules = null;

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        $key = $this->argument('key');

        $this->rules = $this->getRules($key);

        if (!$this->rules) {
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

        if (!$this->validateUpstreamEnvironment($config, $key)) {
            return 1;
        }

        $value = Arr::get($config, $key);

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

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $key = $this->argument('key');

        if (!$this->validateUpstreamEnvironment($config, $key)) {
            return 1;
        }

        $validator = Validator::make(
            ['data' => $value],
            ['data' => $this->rules[$key]]
        );

        if ($validator->fails()) {
            foreach ($validator->getMessageBag()->all() as $message) {
                $this->error($message);
            }

            return 1;
        }

        $value = $this->castValue($key, $value);

        Arr::set($config, $key, $value);

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

    protected function getRules($key)
    {
        switch ($key) {
            case 'aws-profile': {
                return [
                    'regex:/^[a-zA-Z0-9-_]+$/',
                ];
            }
            case 'cloud-environments.stage.domain':
            case 'cloud-environments.production.domain': {
                return [
                    'regex:/^[a-z0-9-\.]+\.[a-z0-9]+$/'
                ];
            }
            case 'cloud-environments.stage.aws-certificate-arn':
            case 'cloud-environments.production.aws-certificate-arn': {
                return [
                    'regex:/^arn:aws:acm:.+:certificate\/.+$/'
                ];
            }
            case 'cloud-environments.stage.stack-deployed':
            case 'cloud-environments.production.stack-deployed': {
                return [
                    Rule::in(['true', 'false']),
                ];
            }
            case 'cloud-environments.stage.db-type':
            case 'cloud-environments.production.db-type': {
                return [
                    Rule::in($this->valid_db_types)
                ];
            }
            case 'cloud-environments.stage.db-storage-gb':
            case 'cloud-environments.production.db-storage-gb': {
                return [
                    'min:' . $this->minimum_db_storage_gb,
                    'max:' . $this->maxmium_db_storage_gb,
                ];
            }
            case 'cloud-environments.stage.cache-type':
            case 'cloud-environments.production.cache-type': {
                return [
                    Rule::in($this->valid_cache_types),
                ];
            }
            case 'cloud-environments.stage.aws-region':
            case 'cloud-environments.production.aws-region': {
                return [
                    Rule::in($this->valid_aws_regions),
                ];
            }
            default: {
                return false;
            }
        }
    }

    protected function castValue($key, $value)
    {
        switch ($key) {
            case 'cloud-environments.stage.stack-deployed':
            case 'cloud-environments.production.stack-deployed': {
                return $value === 'true';
            }
            case 'cloud-environments.stage.db-storage-gb':
            case 'cloud-environments.production.db-storage-gb': {
                return (int) $value;
            }
            default: {
                return (string) $value;
            }
        }
    }
}
