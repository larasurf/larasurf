<?php

namespace LaraSurf\LaraSurf\Commands;

use Aws\Ssm\Exception\SsmException;
use Aws\Ssm\SsmClient;
use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentArgument;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Env extends Command
{
    use InteractsWithLaraSurfConfig;
    use InteractsWithAws;
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
        'us-east-1', // todo: update
    ];

    public function handle()
    {
        if (!$this->validateEnvironmentArgument()) {
            return 1;
        }

        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    protected function handleInit()
    {
        $aws_region = $this->argument('arg1');

        if (!$aws_region) {
            $this->error('AWS region must be specified');

            return 1;
        }

        if (!in_array($aws_region, $this->valid_aws_regions)) {
            $this->error('Invalid AWS region specified');

            return 1;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        $existed = false;

        if ($config['schema-version'] === 1) {
            if (isset($config['upstream-environments'][$environment])) {
                $this->warn("Environment '$environment' already exists in larasurf.json");

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
            $this->warn("Environment '$environment' already exists in larasurf.json");
        }

        return 0;
    }

    protected function handleExists()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return 1;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if ($config['schema-version'] === 1) {
            $exists = in_array($name, $config['upstream-environments'][$environment]['variables']);
        } else {
            $exists = false;
        }

        $client = $this->getSsmClient($config, $environment);

        $path = $this->getParameterPath($config, $environment, $name);

        if ($exists) {
            try {
                $client->getParameter([
                    'Name' => $path,
                ]);
            } catch (SsmException $exception) {
                $exists = false;
            }
        }

        if ($exists) {
            $this->info("Environment variable '$name' exists in the '$environment' environment");
        } else {
            $this->warn("Environment variable '$name' does not exist in the '$environment' environment");
        }

        return 0;
    }

    protected function handleGet()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return 1;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getSsmClient($config, $environment);

        $path = $this->getParameterPath($config, $environment, $name);

        $result = $client->getParameter([
            'Name' => $path,
            'WithDecryption' => true,
        ]);

        $value = $result['Parameter']['Value'];

        if ($value !== null) {
            $this->line($value);
        } else {
            $this->warn("Environment variable '$name' does not exist in the '$environment' environment");
        }

        return 0;
    }

    protected function handlePut()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return 1;
        }

        $value = $this->argument('arg2');

        if (!$value) {
            $this->error('Environment variable value must be specified');

            return 1;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return;
        }

        $client = $this->getSsmClient($config, $environment);

        $path = $this->getParameterPath($config, $environment, $name);

        $args = [
            'Name' => $path,
            'Type' => 'SecureString',
            'Value' => $value,
        ];

        $parameter_exists = in_array($name, $config['upstream-environments'][$environment]['variables']);

        if ($parameter_exists) {
            $args['Overwrite'] = true;
        } else {
            $args['Tags'] = [
                [
                    'Key' => 'Environment',
                    'Value' => $environment,
                ],
            ];
        }

        $client->putParameter($args);

        $this->info("Parameter Store parameter '$path' written successfully");

        $this->writeEnvironmentVariableToLaraSurfConfig($config, $environment, $name);

        return 0;
    }

    protected function handlePutLocal()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return 1;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $this->writeEnvironmentVariableToLaraSurfConfig($config, $environment, $name);

        return 0;
    }

    protected function handleDelete()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return 1;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getSsmClient($config, $environment);

        $path = $this->getParameterPath($config, $environment, $name);

        $client->deleteParameter([
            'Name' => $path,
        ]);

        $this->info("Parameter Store parameter '$path' deleted successfully");

        $this->deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name);

        return 0;
    }

    protected function handleDeleteLocal()
    {
        $name = $this->getEnvironmentVariableNameArgument();

        if (!$name) {
            return 1;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $this->deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name);

        return 0;
    }

    protected function handleList()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (!empty($config['upstream-environments'][$environment]['variables'])) {
            $this->info(implode(PHP_EOL, $config['upstream-environments'][$environment]['variables']));
        } else {
            $this->warn("Environment '$environment' has no variables in larasurf.json");
        }

        return 0;
    }

    protected function handleListValues()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getSsmClient($config, $environment);

        $path = $this->getParameterPath($config, $environment);

        $results = $client->getParametersByPath([
            'Path' => $path,
            'WithDecryption' => true,
        ]);

        $keys_values = array_map(function ($parameter) {
            return "{$parameter['Name']}: {$parameter['Value']}";
        }, $results['Parameters']);

        $this->info(implode(PHP_EOL, $keys_values));

        return 0;
    }

    protected function validateEnvironmentExistsInConfig(array $config, string $environment)
    {
        if ($config['schema-version'] === 1) {
            if (isset($config['upstream-environments'][$environment])) {
                return true;
            }
        }

        $this->error("Environment '$environment' does not exist in larasurf.json");

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

            if ($key !== false) {
                unset($config['upstream-environments'][$environment]['variables'][$key]);

                $existed = true;
            }
        }

        if ($existed) {
            $this->writeLaraSurfConfig($config);
        } else {
            $this->warn("Environment variable '$name' did not exist in larasurf.json for environment '$environment'");
        }
    }

    protected function getSsmClient($config, $environment)
    {
        if ($config['schema-version'] === 1) {
            return new SsmClient([
                'version' => 'latest',
                'region' => $config['upstream-environments'][$environment]['aws-region'],
                'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
            ]);
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }

    protected function getParameterPath($config, $environment, $parameter = null)
    {
        $parameter = $parameter ?? '';

        if ($config['schema-version'] === 1) {
            return '/' . $config['project-name'] . '/' . $environment . '/' . $parameter;
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }
}
