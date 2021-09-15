<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\HasTimer;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithGitFiles;
use LaraSurf\LaraSurf\Constants\Cloud;
use PDO;

class CloudStacks extends Command
{
    use HasSubCommands;
    use HasEnvironmentOption;
    use HasTimer;
    use InteractsWithAws;
    use InteractsWithGitFiles;

    const COMMAND_STATUS = 'status';
    const COMMAND_CREATE = 'create';
    const COMMAND_UPDATE = 'update';
    const COMMAND_DELETE = 'delete';
    const COMMAND_WAIT = 'wait';
    const COMMAND_OUTPUT = 'output';

    protected $signature = 'larasurf:cloud-stacks
                            {--environment=null : The environment: \'stage\' or \'production\'}
                            {--key=null : The key for the output command}
                            {subcommand : The subcommand to run: \'status\', \'create\', \'update\', \'delete\', or \'wait\'}';

    protected $description = 'Manage application environment variables in cloud environments';

    protected array $commands = [
        self::COMMAND_STATUS => 'handleStatus',
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_UPDATE => 'handleUpdate',
        self::COMMAND_DELETE => 'handleDelete',
        self::COMMAND_WAIT => 'handleWait',
        self::COMMAND_OUTPUT => 'handleOutput',
    ];

    public function handleStatus()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $status = $this->awsCloudFormation($env)->stackStatus();

        if (!$status) {
            $this->warn("Stack for '$env' environment does not exist");

            return 1;
        }

        $this->line($status);

        return 0;
    }

    public function handleOutput()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $key = $this->option('key');

        if (!$key || $key === 'null') {
            $this->error('The --key option is required for stack output');
        }

        $aws_region = static::larasurfConfig()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error("AWS region is not set for the '$env' environment; create image repositories first");

            return 1;
        }

        $cloudformation = $this->awsCloudFormation($env, $aws_region);

        if (!$cloudformation->stackStatus()) {
            $this->error("Stack doesn't exist yet for the '$env' environment");

            return 1;
        }

        $output = $this->awsCloudFormation()->stackOutput($key);

        if (!$output) {
            $this->error("Failed to get output for key '$key'");

            return 1;
        }

        $this->line($output);

        return 0;
    }

    public function handleCreate()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $branch = $env === Cloud::ENVIRONMENT_PRODUCTION ? 'main' : 'stage';

        if (!$this->gitIsOnBranch($branch)) {
            $this->error("Must be on the $branch branch to create a stack for this environment");

            return 1;
        }

        $path = CloudFormationClient::templatePath();

        if (!File::exists($path)) {
            $this->error("CloudFormation template does not exist at path '$path'");

            return 1;
        }

        $aws_region = static::larasurfConfig()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error("AWS region is not set for the '$env' environment; create image repositories first");

            return 1;
        }

        $current_commit = $this->gitCurrentCommit($branch);

        if (!$current_commit) {
            return 1;
        }

        $ecr = $this->awsEcr($env, $aws_region);

        $application_image_tag = 'commit-' . $current_commit;
        $webserver_image_tag = 'commit-' . $current_commit;

        $application_repo_name = $this->awsEcrRepositoryName($env, 'application');
        $webserver_repo_name = $this->awsEcrRepositoryName($env, 'webserver');

        $this->line("Checking if application and webserver images exist...");

        if (!$ecr->imageTagExists($application_repo_name, $application_image_tag)) {
            $this->error("Failed to find tag '$application_image_tag' in ECR repository '$application_repo_name'");
            $this->line('Is CircleCI finished building and publishing the images?');

            return 1;
        }

        if (!$ecr->imageTagExists($webserver_repo_name, $webserver_image_tag)) {
            $this->error("Failed to find tag '$webserver_image_tag' in ECR repository '$webserver_repo_name'");

            return 1;
        }

        $cloudformation = $this->awsCloudFormation($env, $aws_region);

        if ($cloudformation->stackStatus()) {
            $this->error("Stack exists for '$env' environment");

            return 1;
        }

        $ssm = $this->awsSsm($env);

        $existing_parameters = $ssm->listParameters();

        if ($existing_parameters) {
            $this->line("The following variables exist for the '$env' environment:");
            $this->line(implode(PHP_EOL, $existing_parameters));
            $delete_params = $this->confirm('Are you sure you\'d like to delete these variables?', false);

            if (!$delete_params) {
                return 0;
            }

            $this->line('Deleting cloud variables...');

            $this->withProgressBar($existing_parameters, function ($parameter) use ($ssm) {
                $ssm->deleteParameter($parameter);
                sleep (1);
            });

            $this->newLine();
        }

        $db_instance_type = $this->askDatabaseInstanceType();

        $this->line('<info>Minimum database storage (GB):</info> ' . Cloud::DB_STORAGE_MIN_GB);
        $this->line('<info>Maximum database storage (GB):</info> ' . Cloud::DB_STORAGE_MAX_GB);

        $db_storage = $this->askDatabaseStorage();

        $cache_node_type = $this->askCacheNodeType();

        $cpu = $this->askTaskDefinitionCpu();

        $memory = $this->askTaskDefinitionMemory($cpu);

        $scale_target_cpu = $this->askScalingTargetCpu();
        $scale_out_cooldown = $this->askScaleOutCooldown();
        $scale_in_cooldown = $this->askScaleInCooldown();

        $domain = $this->ask('Fully qualified domain name?');

        $route53 = $this->awsRoute53();

        $this->line('Finding hosted zone from domain...');

        $root_domain = $this->rootDomainFromFullDomain($domain);

        $hosted_zone_id = $route53->hostedZoneIdFromRootDomain($root_domain);

        if (!$hosted_zone_id) {
            $this->error("Hosted zone for domain '$domain' could not be found");

            return 0;
        }

        $this->line("<info>Hosted zone found with ID:</info> $hosted_zone_id");

        $acm_arn = $this->findOrCreateAcmCertificateArn($env, $domain, $hosted_zone_id);

        $ec2 = $this->awsEc2($env);

        $this->line('Creating prefix lists...');

        $database_prefix_list_id = $ec2->createPrefixList('database', 'me');
        $application_prefix_list_id = $ec2->createPrefixList('application', 'me');

        $this->startTimer();

        $this->line("Creating stack for '$env' environment...");

        $db_username = Str::substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 1) . Str::random(random_int(10, 15));
        $db_password = Str::random(random_int(32, 40));

        $application_image = $ecr->repositoryUri($this->awsEcrRepositoryName($env, 'application')) . ':' . $application_image_tag;
        $webserver_image = $ecr->repositoryUri($this->awsEcrRepositoryName($env, 'webserver')) . ':' . $webserver_image_tag;

        $cloudformation->createStack(
            false,
            $domain,
            $root_domain,
            $hosted_zone_id,
            $acm_arn,
            $db_storage,
            $db_instance_type,
            $db_username,
            $db_password,
            $cache_node_type,
            $application_image,
            $webserver_image,
            $cpu,
            $memory,
            $database_prefix_list_id,
            $application_prefix_list_id,
            $scale_out_cooldown,
            $scale_in_cooldown,
            $scale_target_cpu
        );

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_CREATE_COMPLETE, $this->getOutput(), 'created', false);

        if (!$result['success']) {
            $this->error("Stack creation failed with status '{$result['status']}'");

            return 1;
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
                'AppAccessPrefixListId',
                'CacheEndpointAddress',
                'CacheEndpointPort',
                'QueueUrl',
                'BucketName',
                'DBSecurityGroupId',
                'ContainersSecurityGroupId',
                'CacheSecurityGroupId',
                'ArtisanTaskDefinitionArn',
                'Subnet1Id',
            ]);

            if (empty($outputs)) {
                sleep(2);
            }
        } while ($tries < $limit && empty($outputs));

        if ($tries >= $limit) {
            $this->error('Failed to get CloudFormation stack outputs');

            return 1;
        }

        $this->line('Creating database schema...');

        $database_name = $this->createDatabaseSchema(
            static::larasurfConfig()->get('project-name'),
            $env,
            $outputs['DBHost'],
            $outputs['DBPort'],
            $db_username,
            $db_password,
        );

        if (!$database_name) {
            $this->error("Failed to create database schema '$database_name'");

            return 1;
        }

        $this->info("Created database schema '$database_name' successfully");


        $parameters = [
            'APP_ENV' => $env,
            'APP_KEY' => 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')),
            'APP_URL' => "https://$domain",
            'CACHE_DRIVER' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $outputs['DBHost'],
            'DB_PORT' => $outputs['DBPort'],
            'DB_DATABASE' => $database_name,
            'DB_USERNAME' => $db_username,
            'DB_PASSWORD' => $db_password,
            'LOG_CHANNEL' => 'errorlog',
            'QUEUE_CONNECTION' => 'sqs',
            'MAIL_MAILER' => $env === Cloud::ENVIRONMENT_PRODUCTION ? 'ses' : 'smtp',
            'AWS_DEFAULT_REGION' => $aws_region,
            'REDIS_HOST' => $outputs['CacheEndpointAddress'],
            'REDIS_PORT' => $outputs['CacheEndpointPort'],
            'SQS_QUEUE' => $outputs['QueueUrl'],
            'AWS_BUCKET' => $outputs['BucketName'],
        ];

        $this->line('Creating cloud variables...');

        $ssm = $this->awsSsm($env);

        foreach ($parameters as $name => $value) {
            $ssm->putParameter($name, $value);
            sleep(1);

            $this->line("<info>Successfully created cloud variable:</info> $name");
        }

        $this->line('Waiting to list cloud variables...');

        do {
            $secrets = $ssm->listParameterArns(true);

            $has_all = true;

            foreach (array_keys($parameters) as $parameter) {
                if (!in_array($parameter, array_keys($secrets))) {
                    $has_all = false;

                    break;
                }
            }

            if (!$has_all) {
                $this->line('Cloud variables still creating, checking again soon...');
                sleep(5);
            }
        } while (!$has_all);

        $this->line('Updating stack with cloud variables...');

        $cloudformation->updateStack(true, $secrets);

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_UPDATE_COMPLETE, $this->getOutput(), 'updated', false);

        if (!$result['success']) {
            $this->error("Stack updating failed with status '{$result['status']}'");

            return 1;
        } else {
            $this->info("Stack updating completed successfully");
        }

        $tries = 0;
        $limit = 10;

        do {
            $updated_outputs = $cloudformation->stackOutput([
                    'ArtisanTaskDefinitionArn',
                    'ContainerClusterArn',
                ]) ?? null;

            if (empty($updated_outputs)) {
                $this->line('Stack outputs are not yet updated, checking again soon...');
                sleep(5);
            }
        } while ($tries < $limit && (empty($updated_outputs) || $updated_outputs['ArtisanTaskDefinitionArn'] === $outputs['ArtisanTaskDefinitionArn']));

        if ($tries >= $limit) {
            $this->error('Failed to get updated CloudFormation outputs');

            return 1;
        }

        $security_groups = [
            $outputs['DBSecurityGroupId'],
            $outputs['CacheSecurityGroupId'],
            $outputs['ContainersSecurityGroupId'],
        ];

        $subnets = [$outputs['Subnet1Id']];

        $this->line('Starting ECS task to run migrations...');

        $ecs = $this->awsEcs($env, $aws_region);
        $task_arn = $ecs->runTask($updated_outputs['ContainerClusterArn'], $security_groups, $subnets, ['php', 'artisan', 'migrate', '--force'], $updated_outputs['ArtisanTaskDefinitionArn']);

        if (!$task_arn) {
            $this->error('Failed to start ECS task to run migrations');

            return 1;
        }

        $this->info('Started ECS task to run migrations successfully');

        $ecs->waitForTaskFinish(
            $updated_outputs['ContainerClusterArn'],
            $task_arn,
            $this->getOutput(),
            'Task has not completed yet, checking again soon...'
        );

        $this->line('Updating application prefix list to allow ingress from this IP...');

        $this->stopTimer();
        $this->displayTimeElapsed();

        $this->line("<info>Visit</info> https://$domain <info>to see your application</info>");

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

        $secrets = $this->awsSsm($env)->listParameterArns(true);

        $updates = $name = $this->choice(
            'Which options would you like to change?',
            [
                '(None)',
                'Domain + ACM certificate ARN',
                'ACM certificate ARN',
                'Database instance type',
                'Database storage size',
                'Cache node type',
                'Task definition CPU + Memory',
                'AutoScaling target CPU percent',
                'AutoScaling scale out cooldown',
                'AutoScaling scale in cooldown',
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
        $new_cache_node_type = null;
        $new_cpu = null;
        $new_memory = null;
        $new_scale_cpu = null;
        $new_scale_out_cooldown = null;
        $new_scale_in_cooldown = null;

        $route53 = $this->awsRoute53();

        if (in_array('ACM certificate ARN', $updates) && in_array('Domain + ACM certificate ARN', $updates)) {
            $index = array_search('ACM certificate ARN', $updates);
            unset($updates[$index]);
        }

        foreach ($updates as $update) {
            switch ($update) {
                case 'Domain + ACM certificate ARN': {
                    $new_domain = $this->ask('Fully qualified domain name?');

                    $root_domain = $this->rootDomainFromFullDomain($new_domain);

                    $new_hosted_zone_id = $route53->hostedZoneIdFromRootDomain($root_domain);

                    if (!$new_hosted_zone_id) {
                        $this->error("Hosted zone for domain '$new_domain' could not be found");

                        return 1;
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
                case 'Cache node type': {
                    $new_cache_node_type = $this->askCacheNodeType();

                    break;
                }
                case 'Task definition CPU + Memory': {
                    $new_cpu = $this->askTaskDefinitionCpu();
                    $new_memory = $this->askTaskDefinitionMemory($new_cpu);

                    break;
                }
                case 'AutoScaling target CPU percent': {
                    $new_scale_cpu = $this->askScalingTargetCpu();

                    break;
                }
                case 'AutoScaling scale out cooldown': {
                    $new_scale_out_cooldown = $this->askScaleOutCooldown();

                    break;
                }
                case 'AutoScaling scale in cooldown': {
                    $new_scale_in_cooldown = $this->askScaleInCooldown();

                    break;
                }
            }
        }

        $this->startTimer();

        $cloudformation->updateStack(
            true,
            $secrets,
            $new_domain,
            $new_domain ? $this->rootDomainFromFullDomain($new_domain) : null,
            $new_hosted_zone_id,
            $new_certificate_arn,
            $new_db_storage,
            $new_db_instance_type,
            $new_cache_node_type,
            $new_cpu,
            $new_memory,
            $new_scale_out_cooldown,
            $new_scale_in_cooldown,
            $new_scale_cpu
        );

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

        if (!$this->confirm("Are you sure you want to delete the stack for the '$env' environment?", false)) {
            return 0;
        }

        $cloudformation = $this->awsCloudFormation($env);

        if (!$cloudformation->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $this->line('Checking database for deletion protection...');

        $outputs = $cloudformation->stackOutput([
            'DBId',
            'DBAdminAccessPrefixListId',
            'AppAccessPrefixListId',
        ]);

        $rds = $this->awsRds($env);
        $ec2 = $this->awsEc2($env);

        if ($outputs) {
            if ($rds->checkDeletionProtection($outputs['DBId'])) {
                $this->warn("Deletion protection is enabled for the '$env' environment's database");

                if (!$this->confirm('Would you like to disable deletion protection and proceed?', false)) {
                    return 0;
                }

                $this->line('Disabling database deletion protection...');

                $rds->modifyDeletionProtection($outputs['DBId'], false);

                $this->info('Deletion protection disabled successfully');
            }

            $this->line('Deleting prefix lists...');

            $ec2->deletePrefixList($outputs['DBAdminAccessPrefixListId']);
            $ec2->deletePrefixList($outputs['AppAccessPrefixListId']);

            $this->info('Deleted prefix lists successfully');
        } else {
            $this->warn('Failed to get stack outputs');
        }

        $this->startTimer();

        $cloudformation->deleteStack();

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_DELETED, $this->getOutput(), 'deleted');

        if (!$result['success']) {
            $this->error("Stack deletion failed with status '{$result['status']}'");
        } else {
            $this->info("Stack deletion completed successfully");
        }

        $this->stopTimer();
        $this->displayTimeElapsed();

        return 0;
    }

    public function handleWait()
    {
        $env = $this->option('environment');

        if (!$env) {
            return 1;
        }

        $result = $this->awsCloudFormation($env)->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_UPDATE_COMPLETE, $this->getOutput(), 'changed');

        $this->line("<info>Stack operation finished with status:</info> {$result['status']}");

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

    protected function askCacheNodeType(): string
    {
        return $this->choice('Cache node type?', Cloud::CACHE_NODE_TYPES, 0);
    }

    protected function askTaskDefinitionCpu(): string
    {
        return $this->choice('Task definition CPU?', Cloud::FARGATE_CPU_VALUES, 0);
    }

    protected function askTaskDefinitionMemory(string $cpu): string
    {
        return $this->choice('Task definition memory?', Cloud::FARGATE_CPU_MEMORY_VALUES_MAP[$cpu] ?? [], 0);
    }

    protected function askScalingTargetCpu(): int
    {
        do {
            $db_storage = (int) $this->ask('Auto Scaling target CPU percent?', 50);
            $valid = $db_storage <= 100 && $db_storage >=10;

            if (!$valid) {
                $this->error('Invalid Auto Scaling target CPU percent');
            }
        } while (!$valid);

        return $db_storage;
    }

    protected function askScaleOutCooldown(): int
    {
        return (int) $this->ask('Auto Scaling scale out cooldown (seconds)?', 10);
    }

    protected function askScaleInCooldown(): int
    {
        return (int) $this->ask('Auto Scaling scale in cooldown (seconds)?', 10);
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
            $this->line('Creating ACM certificate...');

            $acm = $this->awsAcm($env);
            $acm_arn = null;

            $dns_record = $acm->requestCertificate(
                $acm_arn,
                $domain,
                AcmClient::VALIDATION_METHOD_DNS,
                $this->getOutput(),
                'Certificate is still being created, checking again soon...'
            );

            $this->line('');
            $this->line('Verifying ACM certificate via DNS record...');

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
            return false;
        }

        return $database_name;
    }
}
