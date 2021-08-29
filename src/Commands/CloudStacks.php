<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\HasTimer;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Constants\Cloud;
use PDO;

class CloudStacks extends Command
{
    use HasSubCommands;
    use HasEnvOption;
    use HasTimer;
    use InteractsWithAws;

    const COMMAND_STATUS = 'status';
    const COMMAND_CREATE = 'create';
    const COMMAND_UPDATE = 'update';
    const COMMAND_DELETE = 'delete';
    const COMMAND_WAIT = 'wait';

    protected $signature = 'larasurf:cloud-stacks
                            {--environment=null : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'status\', \'create\', \'update\', \'delete\', or \'wait\'}';

    protected $description = 'Manage application environment variables in cloud environments';

    protected array $commands = [
        self::COMMAND_STATUS => 'handleStatus',
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_UPDATE => 'handleUpdate',
        self::COMMAND_DELETE => 'handleDelete',
        self::COMMAND_WAIT => 'handleWait',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    public function handleStatus()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $status = $this->awsCloudFormation($env)->stackStatus();

        if (!$status) {
            $this->warn("Stack for '$env' environment does not exist");
        } else {
            $this->getOutput()->writeln("<info>Status:</info> $status");
        }

        return 0;
    }

    public function handleCreate()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $path = CloudFormationClient::templatePath();

        if (!File::exists($path)) {
            $this->error("CloudFormation template does not exist at path '$path'");

            return 1;
        }

        $aws_region = $this->choice('Which AWS region would you like to create the stack in?', Cloud::AWS_REGIONS, 0);

        $cloudformation = $this->awsCloudFormation($env, $aws_region);

        if ($cloudformation->stackStatus()) {
            $this->error("Stack already exists for '$env' environment");

            return 1;
        }

        $ssm = $this->awsSsm($env);

        $existing_parameters = $ssm->listParameters();

        if ($existing_parameters) {
            $this->info("The following variables exist for the '$env' environment:");
            $this->getOutput()->writeln(implode(PHP_EOL, $existing_parameters));
            $delete_params = $this->confirm('Are you sure you\'d like to delete these variables?', false);

            if (!$delete_params) {
                return 0;
            }

            foreach ($existing_parameters as $parameter) {
                $ssm->deleteParameter($parameter);
            }
        }

        $db_instance_type = $this->askDatabaseInstanceType();

        $this->getOutput()->writeln('<info>Minimum database storage (GB):</info> ' . Cloud::DB_STORAGE_MIN_GB);
        $this->getOutput()->writeln('<info>Maximum database storage (GB):</info> ' . Cloud::DB_STORAGE_MAX_GB);

        $db_storage = $this->askDatabaseStorage();

        $domain = $this->ask('Fully qualified domain name?');

        $route53 = $this->awsRoute53();

        $this->info('Finding hosted zone from domain...');

        $hosted_zone_id = $route53->hostedZoneIdFromDomain($domain);

        if (!$hosted_zone_id) {
            $this->error("Hosted zone for domain '$domain' could not be found");

            return 0;
        }

        $this->info("Hosted zone found with ID '$hosted_zone_id'");

        $acm_arn = $this->findOrCreateAcmCertificateArn($env, $domain, $hosted_zone_id);

        $this->startTimer();

        $this->newLine();
        $this->info("Creating stack for '$env' environment...");

        $db_username = Str::random(random_int(16, 32));
        $db_password = Str::random(random_int(32, 40));

        $cloudformation->createStack(
            $domain,
            $hosted_zone_id,
            $acm_arn,
            $db_storage,
            $db_instance_type,
            $db_username,
            $db_password
        );

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_CREATE_COMPLETE, $this->getOutput(), 'created');

        if (!$result['success']) {
            $this->error("Stack creation failed with status '{$result['status']}'");
        } else {
            $this->info("Stack creation completed successfully");
        }

        $tries = 0;
        $limit = 10;

        do {
            $outputs = $cloudformation->stackOutput([
                'DomainName',
                'DBHost',
                'DBPort',
                'DBAdminAccessPrefixListId',
            ]);

            if (empty($outputs)) {
                sleep(2);
            }
        } while ($tries < $limit && empty($outputs));

        if ($tries >= $limit) {
            $this->error('Failed to get CloudFormation stack outputs');

            return 1;
        }

        $this->info('Allowing database ingress from current IP address...');

        $ec2 = $this->awsEc2($env);

        $ec2->allowIpPrefixList($outputs['DBAdminAccessPrefixListId'], 'me');

        $this->info('Creating database schema...');

        $database_name = $this->createDatabaseSchema(
            static::config()->get('project-name'),
            $env,
            $outputs['DBHost'],
            $outputs['DBPort'],
            $db_username,
            $db_password,
        );

        $this->info('Revoking database ingress from current IP address...');

        $ec2->revokeIpPrefixList($outputs['DBAdminAccessPrefixListId'], 'me');

        $parameters = [
            'APP_ENV' => $env,
            'APP_KEY' => 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')),
            'CACHE_DRIVER' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $outputs['DBHost'],
            'DB_PORT' => $outputs['DBPort'],
            'DB_DATABASE' => $database_name,
            'LOG_CHANNEL' => 'errorlog',
            'QUEUE_CONNECTION' => 'sqs',
            'MAIL_DRIVER' => $env === Cloud::ENVIRONMENT_PRODUCTION ? 'ses' : 'smtp',
            'AWS_DEFAULT_REGION' => static::config()->get("environments.$env.aws-region"),
        ];

        $this->info('Created default cloud variables...');

        $this->createSsmParameters($env, $parameters);

        $this->info("Updating LaraSurf configuration...");

        static::config()->set("environments.$env.aws-region", $aws_region);

        if (!static::config()->write()) {
            $this->error("Failed to update LaraSurf configuration");

            return 1;
        }

        $this->info("Updated LaraSurf configuration successfully");

        $this->stopTimer();
        $this->displayTimeElapsed();

        return 0;
    }

    public function handleUpdate()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $path = CloudFormationClient::templatePath();

        if (!File::exists($path)) {
            $this->error("CloudFormation template does not exist at path '$path'");

            return 1;
        }

        $cloudformation = $this->awsCloudFormation($env);

        if (!$cloudformation->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $updates = $name = $this->choice(
            'Which options would you like to change?',
            [
                '(None)',
                'Domain + ACM certificate ARN',
                'ACM certificate ARN',
                'Database instance type',
                'Database storage size',
            ],
            0,
            null,
            true
        );

        $new_domain = null;
        $new_hosted_zone_id = null;
        $new_certificate_arn = null;
        $new_db_instance_type = null;
        $new_db_storage = null;

        $route53 = $this->awsRoute53();

        if (in_array('ACM certificate ARN', $updates) && in_array('Domain + ACM certificate ARN', $updates)) {
            $index = array_search('ACM certificate ARN', $updates);
            unset($updates[$index]);
        }

        foreach ($updates as $update) {
            switch ($update) {
                case 'Domain + ACM certificate ARN': {
                    $new_domain = $this->ask('Fully qualified domain name?');

                    $new_hosted_zone_id = $route53->hostedZoneIdFromDomain($new_domain);

                    if (!$new_hosted_zone_id) {
                        $this->error("Hosted zone for domain '$new_domain' could not be found");

                        return 0;
                    }

                    $new_certificate_arn = $this->findOrCreateAcmCertificateArn($env, $new_domain, $new_hosted_zone_id);

                    break;
                }
                case 'ACM certificate ARN': {
                    $new_certificate_arn = $this->askAcmCertificateArn();

                    break;
                }
                case 'Database instance type': {
                    $new_db_instance_type = $this->askDatabaseInstanceType();

                    break;
                }
                case 'Database storage size': {
                    $new_db_storage = $this->askDatabaseStorage();

                    break;
                }
            }
        }

        $this->startTimer();

        $cloudformation->updateStack($new_domain, $new_hosted_zone_id, $new_certificate_arn, $new_db_storage, $new_db_instance_type);

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_UPDATE_COMPLETE, $this->getOutput(), 'updated');

        if (!$result['success']) {
            $this->error("Stack update failed with status '{$result['status']}'");
        } else {
            $this->info("Stack update completed successfully");
        }

        $this->stopTimer();
        $this->displayTimeElapsed();

        return 0;
    }

    public function handleDelete()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        if ($env === Cloud::ENVIRONMENT_PRODUCTION) {
            if (!$this->confirm('Have you manually disabled deletion protection from the RDS database instance?', false)) {
                $region = static::config()->get("environments.$env.aws-region");

                $this->error('RDS database instance deletion protection must be manually disabled before deleting the stack');
                $this->getOutput()->writeln("https://console.aws.amazon.com/rds/home?region=$region");

                return 1;
            }
                
        }

        if (!$this->confirm("Are you sure you want to delete the stack for the '$env' environment?", false)) {
            return 0;
        }

        $cloudformation = $this->awsCloudFormation($env);

        if (!$cloudformation->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $cloudformation->deleteStack();

        $this->info('Stack deletion initiated');

        static::config()->set("environments.$env", null);
        static::config()->write();

        return 0;
    }

    public function handleWait()
    {
        $env = $this->option('environment');

        if (!$env) {
            return 1;
        }

        $result = $this->awsCloudFormation($env)->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_UPDATE_COMPLETE, $this->getOutput(), 'changed');

        $this->getOutput()->writeln("<info>Stack operation finished with status:</info> {$result['status']}");

        return 0;
    }

    protected function askAcmCertificateArn(): string
    {
        do {
            $acm_arn = $this->ask('ACM certificate ARN?');
            $valid = preg_match('/^arn:aws:acm:.+:certificate\/.+$/', $acm_arn);

            if (!$valid) {
                $this->error('Invalid ACM certificate ARN');
            }
        } while (!$valid);

        return $acm_arn;
    }

    protected function askDatabaseInstanceType(): string
    {
        return $this->choice('Database instance type?', Cloud::DB_INSTANCE_TYPES, 0);
    }

    protected function askDatabaseStorage(): string
    {
        do {
            $db_storage = (int) $this->ask('Database storage (GB)?', Cloud::DB_STORAGE_MIN_GB);
            $valid = $db_storage <= Cloud::DB_STORAGE_MAX_GB && $db_storage >= Cloud::DB_STORAGE_MIN_GB;

            if (!$valid) {
                $this->error('Invalid database storage size');
            }
        } while (!$valid);

        return $db_storage;
    }

    protected function findOrCreateAcmCertificateArn(string $env, string $domain, string $hosted_zone_id): string
    {
        if ($this->confirm('Is there a preexisting ACM certificate you\'d like to use?', false)) {
            $acm_arn = $this->askAcmCertificateArn();
        } else {
            $this->info('Creating ACM certificate...');

            $acm = $this->awsAcm($env);
            $acm_arn = null;

            $dns_record = $acm->requestCertificate(
                $acm_arn,
                $domain,
                AcmClient::VALIDATION_METHOD_DNS,
                $this->getOutput(),
                'Certificate is still being created, checking again soon...'
            );

            $this->getOutput()->writeln('');
            $this->info('Verifying ACM certificate via DNS record...');

            $route53 = $this->awsRoute53();

            $changed_id = $route53->upsertDnsRecords($hosted_zone_id, [$dns_record]);

            $route53->waitForChange(
                $changed_id,
                $this->getOutput(),
                'DNS record update is still pending, checking again soon...'
            );

            $acm->waitForPendingValidation(
                $acm_arn,
                $this->getOutput(),
                'ACM certificate validation is still pending, checking again soon...'
            );

            $this->info("Verified ACM certificate for domain '$domain' successfully");
        }

        return $acm_arn;
    }

    protected function createDatabaseSchema(string $project_name, string $environment, string $db_host, string $db_port, string $db_username, string $db_password)
    {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;', $db_host, $db_port), $db_username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $database_name = str_replace('-', '_', $project_name) . '_' . $environment;

        $result = $pdo->exec(sprintf(
            'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s;',
            $database_name,
            'utf8mb4',
            'utf8mb4_unicode_ci'
        ));

        if ($result === false) {
            $this->error("Failed to create database schema '$database_name'");

            return false;
        }

        $this->info("Created database schema '$database_name' successfully");

        return $database_name;
    }

    protected function createSsmParameters(string $environment, array $parameters)
    {
        $ssm = $this->awsSsm($environment);

        foreach ($parameters as $name => $value) {
            $ssm->putParameter($name, $value);

            $this->info("Successfully created cloud variable '$name'");
        }
    }
}
