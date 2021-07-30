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
    const COMMAND_EXISTS = 'exists';
    const COMMAND_GET = 'get';
    const COMMAND_PUT = 'put';
    const COMMAND_PUT_LOCAL = 'put-local';
    const COMMAND_DELETE = 'delete';
    const COMMAND_DELETE_LOCAL = 'delete-local';
    const COMMAND_LIST = 'list';
    const COMMAND_LIST_VALUES = 'list-values';

    protected $signature = 'larasurf:env {subcommand} {environment} {arg1?} {arg2?}';

    protected $description = 'Manipulate environment variables for an upstream environment';

    protected $commands = [
        self::COMMAND_INIT => 'handleInit',
        self::COMMAND_EXISTS => 'handleExists',
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_PUT => 'handlePut',
        self::COMMAND_PUT_LOCAL => 'handlePutLocal',
        self::COMMAND_DELETE => 'handleDelete',
        self::COMMAND_DELETE_LOCAL => 'handleDeleteLocal',
        self::COMMAND_LIST => 'handleList',
        self::COMMAND_LIST_VALUES => 'handleListValues',
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
        $aws_region = $this->argument('arg1');

        if (!$aws_region) {
            $this->error('AWS region must be specified');

            return;
        }

        if (!in_array($aws_region, $this->valid_aws_regions)) {
            $this->error('Invalid AWS region specified');

            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        $existed = false;

        if ($config['schema-version'] === 1) {
            if (isset($config['upstream-environments'][$environment])) {
                $this->warn("Environment $environment already exists in larasurf.json");

                $existed = true;
            } else {
                $config['upstream-environments'][$environment]['aws-region'] = $aws_region;
                $config['upstream-environments'][$environment]['stack-deployed'] = false;
                $config['upstream-environments'][$environment]['variables'] = [];
            }
        }

        if (!$existed) {
            $this->writeLaraSurfConfig($config);
        } else {
            $this->warn("Environment $environment already exists in larasurf.json");
        }
    }

    protected function handleExists()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        if ($config['schema-version'] === 1) {
            $exists = isset($config['upstream-environments'][$environment]['variables'][$name]);
        } else {
            $exists = false;
        }

        if ($exists) {
            // todo: check parameter store

            $exists = true;
        }

        if ($exists) {
            $this->info("Environment variable '$name' exists in the $environment environment");
        } else {
            $this->warn("Environment variable '$name' does not exist in the $environment environment");
        }
    }

    protected function handleGet()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return;
        }

        // todo: check parameter store
        $value = 'foo';

        if ($value !== null) {
            $this->info($value);
        } else {
            $this->warn("Environment variable '$name' does not exist in the $environment environment");
        }
    }

    protected function handlePut()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return;
        }

        $value = $this->argument('arg2');

        if (!$value) {
            $this->error('Environment variable value must be specified');

            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return;
        }

        // todo: write to parameter store
        $this->info('ToDo: write to parameter store');

        $this->writeEnvironmentVariableToLaraSurfConfig($config, $environment, $name);
    }

    protected function handlePutLocal()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return;
        }

        $this->writeEnvironmentVariableToLaraSurfConfig($config, $environment, $name);
    }

    protected function handleDelete()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return;
        }

        // todo: delete from parameter store
        $this->info('ToDo: delete from parameter store');

        $this->deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name);
    }

    protected function handleDeleteLocal()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return;
        }

        $this->deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name);
    }

    protected function handleList()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return;
        }

        if (!empty($config['upstream-environments'][$environment]['variables'])) {
            $this->info(implode(PHP_EOL, $config['upstream-environments'][$environment]['variables']));
        } else {
            $this->warn("Environment $environment has no variables in larasurf.json");
        }
    }

    protected function handleListValues()
    {
        $this->info('ToDo: handle list values via parameter store');
    }

    protected function validateEnvironmentExistsInConfig(array $config, string $environment)
    {
        if ($config['schema-version'] === 1) {
            return isset($config['upstream-environments'][$environment]);
        }

        $this->error("Environment $environment does not exist in larasurf.json");

        return false;
    }

    protected function getEnvironmentVariableNameArgument()
    {
        $name = $this->argument('arg1');

        if (!$name) {
            $this->error('Environment variable name must be specified');

            return false;
        }

        $regex = '/^[A-Z0-9_]+$/';

        if (!preg_match($regex, $name)) {
            $this->error("Invalid environment variable name '$name' doesn't match $regex");

            return false;
        }

        return $name;
    }

    protected function writeEnvironmentVariableToLaraSurfConfig($config, $environment, $name)
    {
        if ($config['schema-version'] === 1) {
            $config['upstream-environments'][$environment]['variables'][] = $name;

            $variables = array_values(array_unique($config['upstream-environments'][$environment]['variables']));

            sort($variables);

            $config['upstream-environments'][$environment]['variables'] = $variables;
        }

        $this->writeLaraSurfConfig($config);
    }

    protected function deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name)
    {
        $existed = false;

        if ($config['schema-version'] === 1) {
            $key = array_search($name, $config['upstream-environments'][$environment]['variables']);

            if ($key) {
                unset($config['upstream-environments'][$environment]['variables'][$key]);

                $existed = true;
            }
        }

        if ($existed) {
            $this->writeLaraSurfConfig($config);
        } else {
            $this->warn("Environment variable '$name' did not exist in larasurf.json for environment $environment");
        }
    }
}
