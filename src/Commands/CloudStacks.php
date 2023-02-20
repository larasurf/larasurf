<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
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
use LaraSurf\LaraSurf\SchemaCreator;

class CloudStacks extends Command
{
    use HasSubCommands;
    use HasEnvironmentOption;
    use HasTimer;
    use InteractsWithAws;
    use InteractsWithGitFiles;

    /**
     * The available subcommands to run.
     */
    const COMMAND_STATUS = 'status';
    const COMMAND_CREATE = 'create';
    const COMMAND_UPDATE = 'update';
    const COMMAND_DELETE = 'delete';
    const COMMAND_WAIT = 'wait';
    const COMMAND_OUTPUT = 'output';

    /**
     * @var string
     */
    protected $signature = 'larasurf:cloud-stacks
                            {--environment=null : The environment: \'stage\' or \'production\'}
                            {--key=null : The key for the output command}
                            {subcommand : The subcommand to run: \'status\', \'create\', \'update\', \'delete\', or \'wait\'}';

    /**
     * @var string
     */
    protected $description = 'Manage application environment variables in cloud environments';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_STATUS => 'handleStatus',
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_UPDATE => 'handleUpdate',
        self::COMMAND_DELETE => 'handleDelete',
        self::COMMAND_WAIT => 'handleWait',
        self::COMMAND_OUTPUT => 'handleOutput',
    ];

    /**
     * Get the status of the stack for the specified environment.
     *
     * @return int
     */
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

    /**
     * Get a stack output for the specified environment.
     *
     * @return int
     */
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

    /**
     * Create the stack for the specified environment with prompts for various configuration options.
     *
     * @return int
     * @throws \JsonException
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException
     * @throws \LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigKeyException
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handleCreate()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $branch = $env === Cloud::ENVIRONMENT_PRODUCTION ? 'main' : 'stage';

        if (!$this->gitIsOnBranch($branch)) {
            $this->error("Must be on the '$branch' branch to create a stack for this environment");

            return 1;
        }

        $path = $this->awsCloudFormation()->templatePath();

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

        $image_tag = $this->confirmProjectImagesExist($env, $aws_region, $current_commit);

        if (!$image_tag) {
            return 1;
        }

        if (!$this->confirmStackDoesntExist($env, $aws_region)) {
            return 1;
        }

        if (!$this->maybeDeleteAllCloudVariables($env)) {
            return 1;
        }

        $db_instance_type = $this->askDatabaseInstanceType();
        $db_storage = $this->askDatabaseStorage();
        $cache_node_type = $this->askCacheNodeType();
        $cpu = $this->askTaskDefinitionCpu();
        $memory = $this->askTaskDefinitionMemory($cpu);
        $scale_min = $this->askScalingMinTasks();
        $scale_max = $this->askScalingMaxTasks($scale_min);
        $scale_target_cpu = $this->askScalingTargetCpu();
        $scale_out_cooldown = $this->askScaleOutCooldown();
        $scale_in_cooldown = $this->askScaleInCooldown();
        $queue_workers = $this->askQueueTasks();
        $domain = $this->askDomain();

        $hosted_zone_id_root_domain = $this->hostedZoneIdFromDomain($domain);

        if (!$hosted_zone_id_root_domain) {
            return 1;
        }

        $acm_arn = $this->findOrCreateAcmCertificateArn($env, $domain, $hosted_zone_id_root_domain['hosted_zone_id']);

        $prefix_lists = $this->createPrefixLists($env);

        $this->startTimer();

        $db_credentials = $this->createStack(
            $env,
            $aws_region,
            $image_tag,
            $domain,
            $hosted_zone_id_root_domain['root_domain'],
            $hosted_zone_id_root_domain['hosted_zone_id'],
            $acm_arn,
            $db_storage,
            $db_instance_type,
            $cache_node_type,
            $cpu,
            $memory,
            $prefix_lists['database'],
            $prefix_lists['application'],
            $scale_out_cooldown,
            $scale_in_cooldown,
            $scale_target_cpu,
            $scale_min,
            $scale_max,
            $queue_workers
        );

        if(!$db_credentials) {
            return 1;
        }

        $outputs = $this->stackOutputs(
            $env,
            $aws_region,
            [
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
            ]
        );

        $database_name = $this->createDatabaseSchema(
            $env,
            $outputs['DBHost'],
            $outputs['DBPort'],
            $db_credentials['username'],
            $db_credentials['password']
        );

        if (!$database_name) {
            return 1;
        }

        $secrets = $this->createCloudVariables($env, [
            'APP_ENV' => $env,
            'APP_KEY' => 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')),
            'APP_URL' => "https://$domain",
            'CACHE_DRIVER' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $outputs['DBHost'],
            'DB_PORT' => $outputs['DBPort'],
            'DB_DATABASE' => $database_name,
            'DB_USERNAME' => $db_credentials['username'],
            'DB_PASSWORD' => $db_credentials['password'],
            'LOG_CHANNEL' => 'errorlog',
            'QUEUE_CONNECTION' => 'sqs',
            'MAIL_MAILER' => $env === Cloud::ENVIRONMENT_PRODUCTION ? 'ses' : 'smtp',
            'AWS_DEFAULT_REGION' => $aws_region,
            'REDIS_HOST' => $outputs['CacheEndpointAddress'],
            'REDIS_PORT' => $outputs['CacheEndpointPort'],
            'SESSION_DRIVER' => 'redis',
            'SQS_QUEUE' => $outputs['QueueUrl'],
            'AWS_BUCKET' => $outputs['BucketName'],
        ]);

        $updated_outputs = $this->updateStackPostCreate($env, $aws_region, $secrets, $outputs['ArtisanTaskDefinitionArn']);

        if (!$updated_outputs) {
            return 1;
        }

        if (!$this->runMigrations(
            $env,
            $aws_region,
            $updated_outputs['ContainerClusterArn'],
            [
                $outputs['DBSecurityGroupId'],
                $outputs['CacheSecurityGroupId'],
                $outputs['ContainersSecurityGroupId'],
            ],
            [$outputs['Subnet1Id']],
            $updated_outputs['ArtisanTaskDefinitionArn']
        )) {
            return 1;
        }

        $this->info("Visit https://$domain to see your application");

        return 0;
    }

    /**
     * Update the stack with any template changes with prompts for configuration changes.
     *
     * @return int
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException
     */
    public function handleUpdate()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $path = $this->awsCloudFormation()->templatePath();

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
                'Cache node type',
                'Task definition CPU + Memory',
                'AutoScaling Min + Max number of Tasks',
                'AutoScaling target CPU percent',
                'AutoScaling scale out cooldown',
                'AutoScaling scale in cooldown',
                'Queue Worker number of Tasks',
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
        $new_scale_min = null;
        $new_scale_max = null;
        $new_queue_workers = null;

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
                case 'AutoScaling Min + Max number of Tasks': {
                    $new_scale_min = $this->askScalingMinTasks();
                    $new_scale_max = $this->askScalingMaxTasks($new_scale_min);

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
                case 'Queue Worker number of Tasks': {
                    $new_queue_workers = $this->askQueueTasks();

                    break;
                }
            }
        }

        $this->startTimer();

        $this->info('Gathering cloud variables...');

        $secrets = $this->awsSsm($env)->listParameterArns(true);

        $this->info('Updating stack...');

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
            $new_scale_cpu,
            $new_scale_min,
            $new_scale_max,
            $new_queue_workers
        );

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_UPDATE_COMPLETE, $this->getOutput(), 'updated');

        if (!$result['success']) {
            $this->error("Stack update failed with status '{$result['status']}'");
            $this->line('Check the laravel.log file for more information');
        } else {
            $this->info("Stack update completed successfully");
        }

        $this->stopTimer();
        $this->displayTimeElapsed();

        return 0;
    }

    /**
     * Delete the stack for the specified environment.
     *
     * @return int
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException
     */
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

        $this->line('Getting stack outputs...');

        $outputs = $cloudformation->stackOutput([
            'DBId',
            'DBAdminAccessPrefixListId',
            'AppAccessPrefixListId',
        ]);

        $this->line('Checking database for deletion protection...');

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

            if ($ec2->deletePrefixList($outputs['DBAdminAccessPrefixListId'])) {
                $this->info('Deleted database prefix list successfully');
            } else {
                $this->warn('Database prefix list not found');
            }

            if ($ec2->deletePrefixList($outputs['AppAccessPrefixListId'])) {
                $this->info('Deleted application prefix list successfully');
            } else {
                $this->warn('Application prefix list not found');
            }
        } else {
            $this->warn('Failed to get stack outputs');

            return 1;
        }

        $this->startTimer();

        $this->line('Deleting stack...');

        $cloudformation->deleteStack();

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_DELETED, $this->getOutput(), 'deleted');

        if (!$result['success']) {
            $this->error("Stack deletion failed with status '{$result['status']}'");
            $this->line('Check the laravel.log file for more information');
        } else {
            $this->info('Stack deletion completed successfully');
        }

        $this->stopTimer();
        $this->displayTimeElapsed();

        return 0;
    }

    /**
     * Wait for the specified environment's stack updates to complete.
     *
     * @return int
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException
     */
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

    /**
     * @param string $environment
     * @param string $aws_region
     * @param string $image_tag
     * @param string $domain
     * @param string $root_domain
     * @param string $hosted_zone_id
     * @param string $acm_arn
     * @param int $db_storage
     * @param string $db_instance_type
     * @param string $cache_node_type
     * @param string $cpu
     * @param string $memory
     * @param string $database_prefix_list_id
     * @param string $application_prefix_list_id
     * @param int $scale_out_cooldown
     * @param int $scale_in_cooldown
     * @param string $scale_target_cpu
     * @return array|false
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException
     */
    protected function createStack(
        string $environment,
        string $aws_region,
        string $image_tag,
        string $domain,
        string $root_domain,
        string $hosted_zone_id,
        string $acm_arn,
        int $db_storage,
        string $db_instance_type,
        string $cache_node_type,
        string $cpu,
        string $memory,
        string $database_prefix_list_id,
        string $application_prefix_list_id,
        int $scale_out_cooldown,
        int $scale_in_cooldown,
        string $scale_target_cpu,
        int $scale_min,
        int $scale_max,
        int $queue_workers
    ): array|false
    {
        $this->line("Creating stack for '$environment' environment...");

        // username must start with a letter and be no more than 16 characters
        $db_username = Str::substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 1) . Str::random(random_int(10, 15));

        // password must be no more than 40 characters
        $db_password = Str::random(random_int(32, 40));

        $ecr = $this->awsEcr($environment, $aws_region);
        $application_image = $ecr->repositoryUri($this->awsEcrRepositoryName($environment, 'application')) . ':' . $image_tag;
        $webserver_image = $ecr->repositoryUri($this->awsEcrRepositoryName($environment, 'webserver')) . ':' . $image_tag;

        $cloudformation = $this->awsCloudFormation($environment, $aws_region);
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
            $scale_target_cpu,
            $scale_min,
            $scale_max,
            $queue_workers
        );

        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_CREATE_COMPLETE, $this->getOutput(), 'created', false);

        if (!$result['success']) {
            $this->error("Stack creation failed with status '{$result['status']}'");
            $this->line('Check the laravel.log file for more information');

            return false;
        }

        $this->info('Stack creation completed successfully');

        return [
            'username' => $db_username,
            'password' => $db_password,
        ];
    }

    /**
     * @param string $environment
     * @param string $aws_region
     * @param array $secrets
     * @param string $old_artisan_task_definition
     * @return array|false
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException
     */
    protected function updateStackPostCreate(string $environment, string $aws_region, array $secrets, string $old_artisan_task_definition): array|false
    {
        $this->line('Updating stack with cloud variables...');

        $cloudformation = $this->awsCloudFormation($environment, $aws_region);
        $cloudformation->updateStack(true, $secrets);
        $result = $cloudformation->waitForStackInfoPanel(CloudFormationClient::STACK_STATUS_UPDATE_COMPLETE, $this->getOutput(), 'updated', false);

        if (!$result['success']) {
            $this->error("Stack updating failed with status '{$result['status']}'");
            $this->line('Check the laravel.log file for more information');

            return false;
        }

        $this->info('Stack update completed successfully');

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
        } while ($tries < $limit && (empty($updated_outputs) || $updated_outputs['ArtisanTaskDefinitionArn'] === $old_artisan_task_definition));

        if ($tries >= $limit) {
            $this->error('Failed to get updated CloudFormation outputs');

            return false;
        }

        return $updated_outputs;
    }

    /**
     * @param string $environment
     * @param string $aws_region
     * @param array $names
     * @return array|false
     */
    protected function stackOutputs(string $environment, string $aws_region, array $names): array|false
    {
        $tries = 0;
        $limit = 10;

        $cloudformation = $this->awsCloudFormation($environment, $aws_region);

        do {
            $outputs = $cloudformation->stackOutput($names);

            if (empty($outputs)) {
                sleep(2);
            }
        } while ($tries < $limit && empty($outputs));

        if ($tries >= $limit) {
            $this->error('Failed to get CloudFormation stack outputs');

            return false;
        }

        return $outputs;
    }

    /**
     * @param string $environment
     * @param string $db_host
     * @param string $db_port
     * @param string $db_username
     * @param string $db_password
     * @return string|false
     */
    protected function createDatabaseSchema(
        string $environment,
        string $db_host,
        string $db_port,
        string $db_username,
        string $db_password,
    ): string|false
    {
        $this->line('Creating database schema...');

        $database_name = app(SchemaCreator::class)->createSchema(
            static::larasurfConfig()->get('project-name'),
            $environment,
            $db_host,
            $db_port,
            $db_username,
            $db_password,
        );

        if (!$database_name) {
            $this->error("Failed to create database schema '$database_name'");

            return false;
        }

        $this->info("Created database schema '$database_name' successfully");

        return $database_name;
    }

    /**
     * @param string $environment
     * @param array $parameters
     * @return array
     */
    protected function createCloudVariables(string $environment, array $parameters): array
    {
        $this->line('Creating cloud variables...');

        $ssm = $this->awsSsm($environment);

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

        return $secrets;
    }

    /**
     * @param string $environment
     * @param string $aws_region
     * @param string $current_commit
     * @return string|false
     */
    protected function confirmProjectImagesExist(string $environment, string $aws_region, string $current_commit): string|false
    {
        $ecr = $this->awsEcr($environment, $aws_region);

        $image_tag = 'commit-' . $current_commit;

        $application_repo_name = $this->awsEcrRepositoryName($environment, 'application');
        $webserver_repo_name = $this->awsEcrRepositoryName($environment, 'webserver');

        $this->line('Checking if application and webserver images exist...');

        if (!$ecr->imageTagExists($application_repo_name, $image_tag)) {
            $this->error("Failed to find tag '$image_tag' in ECR repository '$application_repo_name'");
            $this->line('Is CircleCI finished building and publishing the images?');

            return false;
        }

        if (!$ecr->imageTagExists($webserver_repo_name, $image_tag)) {
            $this->error("Failed to find tag '$image_tag' in ECR repository '$webserver_repo_name'");
            $this->line('Is CircleCI finished building and publishing the images?');

            return false;
        }

        return $image_tag;
    }

    /**
     * @param string $environment
     * @param string $aws_region
     * @return bool
     */
    protected function confirmStackDoesntExist(string $environment, string $aws_region): bool
    {
        $this->line('Checking if stack exists...');

        $cloudformation = $this->awsCloudFormation($environment, $aws_region);

        if ($cloudformation->stackStatus()) {
            $this->error("Stack already exists for '$environment' environment");

            return false;
        }

        return true;
    }

    /**
     * @param string $environment
     * @return bool
     */
    protected function maybeDeleteAllCloudVariables(string $environment)
    {
        $ssm = $this->awsSsm($environment);

        $existing_parameters = $ssm->listParameters();

        if ($existing_parameters) {
            $this->line("The following variables exist for the '$environment' environment:");
            $this->line(implode(PHP_EOL, $existing_parameters));
            $delete_params = $this->confirm('Are you sure you\'d like to delete these variables?', false);

            if (!$delete_params) {
                return false;
            }

            $this->line('Deleting cloud variables...');

            $this->withProgressBar($existing_parameters, function ($parameter) use ($ssm) {
                $ssm->deleteParameter($parameter);
                sleep (1);
            });

            $this->newLine();
        }

        return true;
    }

    /**
     * @return string
     */
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

    /**
     * @param string $domain
     * @return array|false
     */
    protected function hostedZoneIdFromDomain(string $domain): array|false
    {
        $route53 = $this->awsRoute53();

        $this->line('Finding hosted zone from domain...');

        $root_domain = $this->rootDomainFromFullDomain($domain);

        $hosted_zone_id = $route53->hostedZoneIdFromRootDomain($root_domain);

        if (!$hosted_zone_id) {
            $this->error("Hosted zone for domain '$domain' could not be found");

            return false;
        }

        $this->line("<info>Hosted zone found with ID:</info> $hosted_zone_id");

        return [
            'hosted_zone_id' => $hosted_zone_id,
            'root_domain' => $root_domain,
        ];
    }

    /**
     * @param string $environment
     * @return array
     */
    protected function createPrefixLists(string $environment): array
    {
        $ec2 = $this->awsEc2($environment);

        $this->line('Creating prefix lists...');

        $database_prefix_list_id = $ec2->createPrefixList('database', 'me');

        $this->info('Created database prefix list successfully');

        $application_prefix_list_id = $ec2->createPrefixList('application', 'me');

        $this->info('Created application prefix list successfully');

        return [
            'database' => $database_prefix_list_id,
            'application' => $application_prefix_list_id,
        ];
    }

    /**
     * @param string $environment
     * @param string $aws_region
     * @param string $container_cluster_arn
     * @param array $security_groups
     * @param array $subnets
     * @param string $artisan_task_definition_arn
     * @return bool
     */
    protected function runMigrations(
        string $environment,
        string $aws_region,
        string $container_cluster_arn,
        array $security_groups,
        array $subnets,
        string $artisan_task_definition_arn
    ): bool
    {
        $this->line('Starting ECS task to run migrations...');

        $ecs = $this->awsEcs($environment, $aws_region);
        $task_arn = $ecs->runTask($container_cluster_arn, $security_groups, $subnets, ['php', 'artisan', 'migrate', '--force'], $artisan_task_definition_arn);

        if (!$task_arn) {
            $this->error('Failed to start ECS task to run migrations');

            return false;
        }

        $this->info('Started ECS task to run migrations successfully');

        $ecs->waitForTaskFinish(
            $container_cluster_arn,
            $task_arn,
            $this->getOutput(),
            'Task has not completed yet, checking again soon...'
        );

        return true;
    }

    /**
     * @return string
     */
    protected function askDomain(): string
    {
        do {
            $domain = $this->ask('Fully qualified domain name?');
        } while (!$domain);

        return $domain;
    }

    /**
     * @return string
     */
    protected function askDatabaseInstanceType(): string
    {
        return $this->choice('Database instance type?', Cloud::DB_INSTANCE_TYPES, 0);
    }

    /**
     * @return string
     */
    protected function askCacheNodeType(): string
    {
        return $this->choice('Cache node type?', Cloud::CACHE_NODE_TYPES, 0);
    }

    /**
     * @return string
     */
    protected function askTaskDefinitionCpu(): string
    {
        return $this->choice('Task definition CPU?', Cloud::FARGATE_CPU_VALUES, 0);
    }

    /**
     * @param string $cpu
     * @return string
     */
    protected function askTaskDefinitionMemory(string $cpu): string
    {
        return $this->choice('Task definition memory?', Cloud::FARGATE_CPU_MEMORY_VALUES_MAP[$cpu] ?? [], 0);
    }

    /**
     * @return int
     */
    protected function askScalingTargetCpu(): int
    {
        do {
            $percent = (int) $this->ask('Auto Scaling target CPU percent?', '50');
            $valid = $percent <= 100 && $percent >=10;

            if (!$valid) {
                $this->error('Invalid Auto Scaling target CPU percent');
            }
        } while (!$valid);

        return $percent;
    }

    /**
     * @return int
     */
    protected function askScalingMinTasks(): int
    {
        do {
            $number = (int) $this->ask('Auto Scaling min number of Tasks?', '5');
            $valid = $number > 0;

            if (!$valid) {
                $this->error('Invalid Auto Scaling min number of Tasks');
            }
        } while (!$valid);

        return $number;
    }

    /**
     * @param int $min
     * @return int
     */
    protected function askScalingMaxTasks(int $min): int
    {
        do {
            $number = (int) $this->ask('Auto Scaling max number of Tasks?', '15');
            $valid = $number >= $min;

            if (!$valid) {
                $this->error('Invalid Auto Scaling max number of Tasks');
            }
        } while (!$valid);

        return $number;
    }

    /**
     * @return int
     */
    protected function askQueueTasks(): int
    {
        do {
            $number = (int) $this->ask('Number of Queue Worker Tasks?', '3');
            $valid = $number >= 0;

            if (!$valid) {
                $this->error('Invalid number of Queue Worker Tasks');
            }
        } while (!$valid);

        return $number;
    }

    /**
     * @return int
     */
    protected function askScaleOutCooldown(): int
    {
        return (int) $this->ask('Auto Scaling scale out cooldown (seconds)?', '10');
    }

    /**
     * @return int
     */
    protected function askScaleInCooldown(): int
    {
        return (int) $this->ask('Auto Scaling scale in cooldown (seconds)?', '10');
    }

    /**
     * @return string
     */
    protected function askDatabaseStorage(): string
    {
        $this->line('<info>Minimum database storage (GB):</info> ' . Cloud::DB_STORAGE_MIN_GB);
        $this->line('<info>Maximum database storage (GB):</info> ' . Cloud::DB_STORAGE_MAX_GB);


        do {
            $db_storage = (int) $this->ask('Database storage (GB)?', Cloud::DB_STORAGE_MIN_GB);
            $valid = $db_storage <= Cloud::DB_STORAGE_MAX_GB && $db_storage >= Cloud::DB_STORAGE_MIN_GB;

            if (!$valid) {
                $this->error('Invalid database storage size');
            }
        } while (!$valid);

        return $db_storage;
    }

    /**
     * @param string $env
     * @param string $domain
     * @param string $hosted_zone_id
     * @return string
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException
     */
    protected function findOrCreateAcmCertificateArn(string $env, string $domain, string $hosted_zone_id): string
    {
        if ($this->confirm('Is there a preexisting ACM certificate you\'d like to use?', false)) {
            $acm_arn = $this->askAcmCertificateArn();
        } else {
            $this->line('Creating ACM certificate...');

            $acm = $this->awsAcm($env);

            $certificate_response = $acm->requestCertificate(
                $domain,
                AcmClient::VALIDATION_METHOD_DNS,
                $this->getOutput(),
                'Certificate is still being created, checking again soon...'
            );

            $dns_record = $certificate_response['dns_record'];
            $acm_arn = $certificate_response['certificate_arn'];

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
}
