<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentArgument;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\HasValidEnvironments;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Env extends Command
{
    use InteractsWithLaraSurfConfig;
    use InteractsWithAws;
    use HasValidEnvironments;
    use HasEnvironmentArgument;
    use HasSubCommand;

    const COMMAND_INIT = 'init';
    const COMMAND_EXISTS = 'exists';
    const COMMAND_GET = 'get';
    const COMMAND_PUT = 'put';
    const COMMAND_PUT_LOCAL = 'put-local';
    const COMMAND_DELETE = 'delete';
    const COMMAND_DELETE_LOCAL = 'delete-local';
    const COMMAND_PURGE = 'purge';
    const COMMAND_PURGE_LOCAL = 'purge-local';
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
        self::COMMAND_PURGE => 'handlePurge',
        self::COMMAND_PURGE_LOCAL => 'handlePurgeLocal',
        self::COMMAND_LIST => 'handleList',
        self::COMMAND_LIST_VALUES => 'handleListValues',
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
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (isset($config['cloud-environments'][$environment])) {
            $this->warn("Environment '$environment' already exists in larasurf.json");

            return 1;
        }

        if ($environment === 'production') {
            $config['cloud-environments'][$environment]['db-type'] = 'db.t2.small';
            $config['cloud-environments'][$environment]['cache-type'] = 'cache.t2.small';
            $config['cloud-environments'][$environment]['db-storage-gb'] = 50;
        } else {
            $config['cloud-environments'][$environment]['db-type'] = 'db.t2.small';
            $config['cloud-environments'][$environment]['cache-type'] = 'cache.t2.micro';
            $config['cloud-environments'][$environment]['db-storage-gb'] = 20;
        }

        $config['cloud-environments'][$environment]['aws-region'] = 'us-east-1';
        $config['cloud-environments'][$environment]['aws-certificate-arn'] = false;
        $config['cloud-environments'][$environment]['aws-hosted-zone-id'] = false;
        $config['cloud-environments'][$environment]['aws-app-prefix-list-id'] = false;
        $config['cloud-environments'][$environment]['aws-db-prefix-list-id'] = false;
        $config['cloud-environments'][$environment]['domain'] = false;
        $config['cloud-environments'][$environment]['stack-deployed'] = false;
        $config['cloud-environments'][$environment]['variables'] = [];

        return $this->writeLaraSurfConfig($config) ? 0 : 1;
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

        $client = $this->getSsmClient($config, $environment);

        if (!$client) {
            return 1;
        }

        $path = $this->getSsmParameterPath($config, $environment);

        $exists = in_array($name, $config['cloud-environments'][$environment]['variables']);

        if ($exists) {
            $results = $client->getParametersByPath([
                'Path' => $path,
            ]);

            $ssm_exists = false;

            if (isset($results['Parameters'])) {
                foreach ($results['Parameters'] as $parameter) {
                    if ($parameter['Name'] === $path . $name) {
                        $ssm_exists = true;

                        break;
                    }
                }
            }

            $exists = $ssm_exists;
        }

        if (!$exists) {
            $this->warn("Environment variable '$name' does not exist in the '$environment' environment");

            return 1;
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

        if (!$client) {
            return 1;
        }

        $path = $this->getSsmParameterPath($config, $environment);

        $result = $client->getParameter([
            'Name' => $path,
            'WithDecryption' => true,
        ]);

        $value = $result['Parameter']['Value'];

        if ($value === null) {
            $this->warn("Environment variable '$name' does not exist in the '$environment' environment");

            return 1;
        }

        $this->line($value);

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
            return 1;
        }

        $client = $this->getSsmClient($config, $environment);

        if (!$client) {
            return 1;
        }

        $var_path = $this->getSsmParameterPath($config, $environment, $name);

        $results = $client->getParametersByPath([
            'Path' => $this->getSsmParameterPath($config, $environment),
        ]);

        $exists = false;

        if (isset($results['Parameters'])) {
            foreach ($results['Parameters'] as $parameter) {
                if ($parameter['Name'] === $var_path) {
                    $exists = true;

                    break;
                }
            }
        }

        $args = [
            'Name' => $var_path,
            'Type' => 'SecureString',
            'Value' => $value,
        ];

        if ($exists) {
            $args['Overwrite'] = true;
        } else {
            $args['Tags'] = [
                [
                    'Key' => 'Project',
                    'Value' => $config['project-name'],
                ],
                [
                    'Key' => 'Environment',
                    'Value' => $environment,
                ],
            ];
        }

        $client->putParameter($args);

        $this->info("Parameter Store parameter '$var_path' written successfully");

        return $this->writeEnvironmentVariableToLaraSurfConfig($config, $environment, $name) ? 0 : 1;
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

        return $this->writeEnvironmentVariableToLaraSurfConfig($config, $environment, $name) ? 0 : 1;
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

        if (!$client) {
            return 1;
        }

        $path = $this->getSsmParameterPath($config, $environment);

        $client->deleteParameter([
            'Name' => $path,
        ]);

        $this->info("Parameter Store parameter '$path' deleted successfully");

        return $this->deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name) ? 0 : 1;
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

        return $this->deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name) ? 0 : 1;
    }

    protected function handlePurge()
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

        if (!$client) {
            return 1;
        }

        $path = $this->getSsmParameterPath($config, $environment);

        $keys = [];
        $next_token = null;

        do {
            $results = $client->getParametersByPath([
                'Path' => $path,
                'NextToken' => $next_token,
            ]);

            $keys = array_merge($keys, array_column($results['Parameters'], 'Name'));

            $next_token = $results['NextToken'] ?? false;

            $done = !$next_token || !$results['Parameters'];
        } while (!$done);

        if (!$keys) {
            $this->warn("No cloud parameters exist for environment '$environment'");

            return 0;
        }

        sort($keys);

        $this->info('Existing cloud parameters:');

        foreach ($keys as $key) {
            $this->getOutput()->writeln($key);
        }

        if (!$this->confirm('Are you sure you\'d like to delete all of the above parameters?', false)) {
            return 0;
        }

        do {
            $to_delete = [];

            for ($i = 0; $i < 10 && $keys; $i++) {
                $to_delete[] = array_shift($keys);
            }

            if ($to_delete) {
                $client->deleteParameters([
                    'Names' => $to_delete,
                ]);
            }
        } while ($to_delete);

        $this->info('Cloud parameters deleted successfully');

        $config['cloud-environments'][$environment]['variables'] = [];

        return $this->writeLaraSurfConfig($config);
    }

    protected function handlePurgeLocal()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $variables = array_map(function ($variable) use ($config, $environment) {
            return $this->getSsmParameterPath($config, $environment, $variable);
        }, $config['cloud-environments'][$environment]['variables']);

        $this->info('Existing local parameters:');

        $this->getOutput()->writeln(implode(PHP_EOL, $variables));

        if (!$this->confirm('Are you sure you\'d like to delete all of the above parameters from larasurf.json?', false)) {
            return 0;
        }

        $config['cloud-environments'][$environment]['variables'] = [];

        return $this->writeLaraSurfConfig($config);
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

        $variables = array_map(function ($variable) use ($config, $environment) {
            return $this->getSsmParameterPath($config, $environment, $variable);
        }, $config['cloud-environments'][$environment]['variables']);

        if (!empty($config['cloud-environments'][$environment]['variables'])) {
            $this->info(implode(PHP_EOL, $variables));
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

        if (!$client) {
            return 1;
        }

        $path = $this->getSsmParameterPath($config, $environment);

        $done = false;
        $keys_values = [];
        $next_token = null;

        do {
            $results = $client->getParametersByPath([
                'Path' => $path,
                'WithDecryption' => true,
                'NextToken' => $next_token,
            ]);

            $keys_values = array_merge($keys_values, array_map(function ($parameter) {
                return "<info>{$parameter['Name']}:</info> {$parameter['Value']}";
            }, $results['Parameters']));

            $next_token = $results['NextToken'] ?? false;

            $done = !$next_token || !$results['Parameters'];
        } while (!$done);

        if ($keys_values) {
            sort($keys_values);

            $this->getOutput()->writeln(implode(PHP_EOL, $keys_values));
        } else {
            $this->warn("Environment '$environment' has no cloud variables");
        }

        return 0;
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
        $config['cloud-environments'][$environment]['variables'][] = $name;

        $variables = array_values(array_unique($config['cloud-environments'][$environment]['variables']));

        sort($variables);

        $config['cloud-environments'][$environment]['variables'] = $variables;

        return $this->writeLaraSurfConfig($config);
    }

    protected function deleteEnvironmentVariableFromLaraSurfConfig($config, $environment, $name)
    {
        $existed = false;

        $key = array_search($name, $config['cloud-environments'][$environment]['variables']);

        if ($key !== false) {
            unset($config['cloud-environments'][$environment]['variables'][$key]);

            $existed = true;
        }

        if ($existed) {
            return $this->writeLaraSurfConfig($config);
        }

        $this->warn("Environment variable '$name' did not exist in larasurf.json for environment '$environment'");

        return true;
    }
}
