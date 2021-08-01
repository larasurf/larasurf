<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Encryption\Encrypter;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentArgument;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Infra extends Command
{
    use InteractsWithLaraSurfConfig;
    use InteractsWithAws;
    use HasEnvironmentArgument;
    use HasSubCommand;

    const COMMAND_CREATE = 'create';
    const COMMAND_DESTROY = 'destroy';

    protected $signature = 'larasurf:infra {subcommand} {environment}';

    protected $description = 'Manipulate the infrastructure for an upstream environment';

    protected $commands = [
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_DESTROY => 'handleDestroy',
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

    protected function handleCreate()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getCloudFormationClient($config, $environment);

        if (!$client) {
            return 1;
        }

        $stack_name = $this->getCloudFormationStackName($config, $environment);

        if (!$stack_name) {
            return 1;
        }

        $infrastructure_template_path = base_path('.cloudformation/infrastructure.yml');

        if (!File::exists($infrastructure_template_path)) {
            $this->error("File '.cloudformation/infrastructure.yml' does not exist");

            return 1;
        }

        $ssm_client = $this->getSsmClient($config, $environment);

        if (!$ssm_client) {
            return 1;
        }

        $path = $this->getSsmParameterPath($config, $environment);

        if (!$path) {
            return 1;
        }

        $results = $ssm_client->getParametersByPath([
            'Path' => $path,
        ]);

        $app_key = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));

        $default_env_vars = [
            'APP_ENV' => $environment,
            'APP_KEY' => $app_key,
            'CACHE_DRIVER' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'LOG_CHANNEL' => 'errorlog',
            'QUEUE_CONNECTION' => 'sqs',
        ];

        foreach ($default_env_vars as $key => $value) {
            $var_path = $this->getSsmParameterPath($config, $environment, $key);
            $exists = false;

            foreach ($results['Parameters'] as $parameter) {
                if ($parameter['Name'] === $var_path) {
                    $this->warn("Parameter $var_path already exists");
                    $exists = true;
                }
            }

            if (!$exists) {
                $ssm_client->putParameter([
                    'Name' => $var_path,
                    'Type' => 'SecureString',
                    'Value' => $value,
                    'Tags' => [
                        [
                            'Key' => 'Project',
                            'Value' => $config['project-name'],
                        ],
                        [
                            'Key' => 'Environment',
                            'Value' => $environment,
                        ],
                    ],
                ]);

                $this->info("Successfully set parameter '$var_path'");
            }
        }

        $template = File::get($infrastructure_template_path);

        $client->createStack([
            'Capabilities' => ['CAPABILITY_IAM'],
            'StackName' => $stack_name,
            'Parameters' => [
                [
                    'ParameterKey' => 'VpcName',
                    'ParameterValue' => "{$config['project-name']}-$environment",
                ],
            ],
            'Tags' => [
                [
                    'Key' => 'Project',
                    'Value' => $config['project-name'],
                ],
                [
                    'Key' => 'Environment',
                    'Value' => $environment,
                ],
            ],
            'TemplateBody' => $template,
        ]);

        $this->info('Stack creation initiated');

        $this->line("See https://console.aws.amazon.com/cloudformation/home?region={$config['upstream-environments'][$environment]['aws-region']} for more information");

        $finished = false;
        $success = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $client->describeStacks([
                'StackName' => $stack_name,
            ]);

            if (isset($result['Stacks'][0]['StackStatus'])) {
                $status = $result['Stacks'][0]['StackStatus'];
                $finished = !str_ends_with($status, '_IN_PROGRESS');

                if ($finished) {
                    $success = $result['Stacks'][0]['StackStatus'] === 'CREATE_COMPLETE';
                } else {
                    $this->line('Stack creation is not yet finished, checking again in 10 seconds...');
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $bar = $this->output->createProgressBar(10);

                $bar->start();

                for ($i = 0; $i < 10; $i++) {
                    sleep(1);
                    $bar->advance();
                }
                
                $bar->finish();
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('Stack failed to be created within 30 minutes');

            return 1;
        } else {
            if ($success) {
                $this->info('Stack created successfully');
            } else {
                $this->error("Stack creation failed with status: '$status'");
                $this->error("See https://console.aws.amazon.com/cloudformation/home?region={$config['upstream-environments'][$environment]['aws-region']} for more information");

                return 1;
            }
        }

        return 0;
    }

    protected function handleDestroy()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getCloudFormationClient($config, $environment);

        if (!$client) {
            return 1;
        }

        $stack_name = $this->getCloudFormationStackName($config, $environment);

        if (!$stack_name) {
            return 1;
        }

        if ($this->confirm("Are you sure you want to destroy the '$environment' environment?")) {
            $client->deleteStack([
                'StackName' => $stack_name,
            ]);

            $this->info('Stack deletion initiated');
            $this->line("See https://console.aws.amazon.com/cloudformation/home?region={$config['upstream-environments'][$environment]['aws-region']} for stack deletion status");
        }

        return 0;
    }
}
