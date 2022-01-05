<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\SchemaCreator;
use LaraSurf\LaraSurf\Tests\TestCase;
use Mockery;

class CloudStacksTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testStatus()
    {
        $status = $this->faker->word;

        $this->mockLaraSurfCloudFormationClient()->shouldReceive('stackStatus')->andReturn($status);

        $this->artisan('larasurf:cloud-stacks status --environment stage')
            ->expectsOutput($status)
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOutput()
    {
        $output = $this->faker->word;

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->andReturn($output);

        $this->artisan('larasurf:cloud-stacks output --environment production --key ' . $this->faker->word)
            ->expectsOutput($output)
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreate()
    {
        Mockery::getConfiguration()->setConstantsMap([
            AcmClient::class => [
                'VALIDATION_METHOD_DNS' => 'DNS',
            ],
            CloudFormationClient::class => [
                'STACK_STATUS_CREATE_COMPLETE' => 'CREATE_COMPLETE',
                'STACK_STATUS_UPDATE_COMPLETE' => 'UPDATE_COMPLETE',
            ]
        ]);

        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        $this->createGitHead('main');
        $this->createValidLaraSurfConfig('local-stage-production');
        $this->createGitCurrentCommit('main', Str::random());

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('imageTagExists')->andReturn(true);
        $ecr->shouldReceive('repositoryUri')->andReturn($this->faker->url);

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('templatePath')->andReturn(base_path('.cloudformation/infrastructure.yml'));
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);
        $cloudformation->shouldReceive('createStack')->andReturn();
        $cloudformation->shouldReceive('waitForStackInfoPanel')->andReturn([
            'success' => true,
            'status' => 'CREATE_COMPLETE',
        ], [
            'success' => true,
            'status' => 'CREATE_COMPLETE',
        ]);
        $cloudformation->shouldReceive('stackOutput')->andReturn([
            'DomainName' => $this->faker->domainName,
            'DBHost' => $this->faker->domainName,
            'DBPort' => $this->faker->numerify('####'),
            'DBAdminAccessPrefixListId' => Str::random(),
            'AppAccessPrefixListId' => Str::random(),
            'CacheEndpointAddress' => $this->faker->url,
            'CacheEndpointPort' => $this->faker->numerify('####'),
            'QueueUrl' => $this->faker->url,
            'BucketName' => $this->faker->word,
            'DBSecurityGroupId' => Str::random(),
            'ContainersSecurityGroupId' => Str::random(),
            'CacheSecurityGroupId' => Str::random(),
            'ArtisanTaskDefinitionArn' => Str::random(),
            'Subnet1Id' => Str::random(),
        ], [
            'ArtisanTaskDefinitionArn' => Str::random(),
            'ContainerClusterArn' => Str::random(),
        ]);
        $cloudformation->shouldReceive('updateStack')->andReturn();

        $existing_parameters = [
            $this->faker->word,
            $this->faker->word,
        ];

        $domain = $this->faker->domainName;

        $ssm = $this->mockLaraSurfSsmClient();
        $ssm->shouldReceive('listParameters')->andReturn($existing_parameters);
        $ssm->shouldReceive('deleteParameter')->andReturn();
        $ssm->shouldReceive('putParameter')->andReturn();
        $ssm->shouldReceive('listParameterArns')->andReturn([
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:' . base64_encode(Str::random()),
            'APP_URL' => "https://$domain",
            'CACHE_DRIVER' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $this->faker->domainName,
            'DB_PORT' => $this->faker->numerify('####'),
            'DB_DATABASE' => $this->faker->word,
            'DB_USERNAME' => Str::random(),
            'DB_PASSWORD' => Str::random(),
            'LOG_CHANNEL' => 'errorlog',
            'QUEUE_CONNECTION' => 'sqs',
            'MAIL_MAILER' => 'ses',
            'AWS_DEFAULT_REGION' => 'us-east-1',
            'REDIS_HOST' => $this->faker->url,
            'REDIS_PORT' => $this->faker->numerify('####'),
            'SQS_QUEUE' => $this->faker->url,
            'AWS_BUCKET' => $this->faker->word,
        ]);

        $hosted_zone_id = Str::random();

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')->andReturn($hosted_zone_id);
        $route53->shouldReceive('upsertDnsRecords')->andReturn(Str::random());
        $route53->shouldReceive('waitForChange')->andReturn();

        $acm = $this->mockLaraSurfAcmClient();
        $acm->shouldReceive('requestCertificate')->andReturn([
            'dns_record' => (new DnsRecord())
                ->setType(DnsRecord::TYPE_CNAME)
                ->setValue(Str::random())
                ->setName(Str::random())
                ->setTtl(random_int(100, 1000)),
            'certificate_arn' => Str::random(),
        ]);
        $acm->shouldReceive('waitForPendingValidation')->andReturn();

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('createPrefixList')->times(2)->andReturn(Str::random());

        $database_name = $this->faker->word;

        $schema_creator = Mockery::mock('overload:' . SchemaCreator::class);
        $schema_creator->shouldReceive('createSchema')->andReturn($database_name);

        $ecs = $this->mockLaraSurfEcsClient();
        $ecs->shouldReceive('runTask')->andReturn(Str::random());
        $ecs->shouldReceive('waitForTaskFinish')->andReturn();

        $this->artisan('larasurf:cloud-stacks create --environment production')
            ->expectsOutput('Checking if application and webserver images exist...')
            ->expectsOutput('Checking if stack exists...')
            ->expectsOutput("The following variables exist for the 'production' environment:")
            ->expectsOutput(implode(PHP_EOL, $existing_parameters))
            ->expectsQuestion('Are you sure you\'d like to delete these variables?', true)
            ->expectsOutput('Deleting cloud variables...')
            ->expectsQuestion('Database instance type?', 'db.t2.small')
            ->expectsOutput('Minimum database storage (GB): 20')
            ->expectsOutput('Maximum database storage (GB): 70368')
            ->expectsQuestion('Database storage (GB)?', '25')
            ->expectsQuestion('Cache node type?', 'cache.t2.micro')
            ->expectsQuestion('Task definition CPU?', '256')
            ->expectsQuestion('Task definition memory?', '512')
            ->expectsQuestion('Auto Scaling target CPU percent?', '50')
            ->expectsQuestion('Auto Scaling scale out cooldown (seconds)?', '10')
            ->expectsQuestion('Auto Scaling scale in cooldown (seconds)?', '10')
            ->expectsQuestion('Fully qualified domain name?', $domain)
            ->expectsOutput('Finding hosted zone from domain...')
            ->expectsOutput("Hosted zone found with ID: $hosted_zone_id")
            ->expectsQuestion('Is there a preexisting ACM certificate you\'d like to use?', false)
            ->expectsOutput('Creating ACM certificate...')
            ->expectsOutput('Verifying ACM certificate via DNS record...')
            ->expectsOutput("Verified ACM certificate for domain '$domain' successfully")
            ->expectsOutput('Creating prefix lists...')
            ->expectsOutput('Created database prefix list successfully')
            ->expectsOutput('Created application prefix list successfully')
            ->expectsOutput("Creating stack for 'production' environment...")
            ->expectsOutput('Stack creation completed successfully')
            ->expectsOutput('Creating database schema...')
            ->expectsOutput("Created database schema '$database_name' successfully")
            ->expectsOutput('Creating cloud variables...')
            ->expectsOutput('Successfully created cloud variable: APP_ENV')
            ->expectsOutput('Successfully created cloud variable: APP_KEY')
            ->expectsOutput('Successfully created cloud variable: APP_URL')
            ->expectsOutput('Successfully created cloud variable: CACHE_DRIVER')
            ->expectsOutput('Successfully created cloud variable: DB_CONNECTION')
            ->expectsOutput('Successfully created cloud variable: DB_HOST')
            ->expectsOutput('Successfully created cloud variable: DB_PORT')
            ->expectsOutput('Successfully created cloud variable: DB_DATABASE')
            ->expectsOutput('Successfully created cloud variable: DB_USERNAME')
            ->expectsOutput('Successfully created cloud variable: DB_PASSWORD')
            ->expectsOutput('Successfully created cloud variable: LOG_CHANNEL')
            ->expectsOutput('Successfully created cloud variable: QUEUE_CONNECTION')
            ->expectsOutput('Successfully created cloud variable: MAIL_MAILER')
            ->expectsOutput('Successfully created cloud variable: AWS_DEFAULT_REGION')
            ->expectsOutput('Successfully created cloud variable: REDIS_HOST')
            ->expectsOutput('Successfully created cloud variable: REDIS_PORT')
            ->expectsOutput('Successfully created cloud variable: SESSION_DRIVER')
            ->expectsOutput('Successfully created cloud variable: SQS_QUEUE')
            ->expectsOutput('Successfully created cloud variable: AWS_BUCKET')
            ->expectsOutput('Waiting to list cloud variables...')
            ->expectsOutput('Updating stack with cloud variables...')
            ->expectsOutput('Stack update completed successfully')
            ->expectsOutput('Starting ECS task to run migrations...')
            ->expectsOutput('Started ECS task to run migrations successfully')
            ->expectsOutput("Visit https://$domain to see your application")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateExistingCertificate()
    {
        Mockery::getConfiguration()->setConstantsMap([
            AcmClient::class => [
                'VALIDATION_METHOD_DNS' => 'DNS',
            ],
            CloudFormationClient::class => [
                'STACK_STATUS_CREATE_COMPLETE' => 'CREATE_COMPLETE',
                'STACK_STATUS_UPDATE_COMPLETE' => 'UPDATE_COMPLETE',
            ]
        ]);

        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        $this->createGitHead('main');
        $this->createValidLaraSurfConfig('local-stage-production');
        $this->createGitCurrentCommit('main', Str::random());

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('imageTagExists')->andReturn(true);
        $ecr->shouldReceive('repositoryUri')->andReturn($this->faker->url);

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('templatePath')->andReturn(base_path('.cloudformation/infrastructure.yml'));
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);
        $cloudformation->shouldReceive('createStack')->andReturn();
        $cloudformation->shouldReceive('waitForStackInfoPanel')->andReturn([
            'success' => true,
            'status' => 'CREATE_COMPLETE',
        ], [
            'success' => true,
            'status' => 'CREATE_COMPLETE',
        ]);
        $cloudformation->shouldReceive('stackOutput')->andReturn([
            'DomainName' => $this->faker->domainName,
            'DBHost' => $this->faker->domainName,
            'DBPort' => $this->faker->numerify('####'),
            'DBAdminAccessPrefixListId' => Str::random(),
            'AppAccessPrefixListId' => Str::random(),
            'CacheEndpointAddress' => $this->faker->url,
            'CacheEndpointPort' => $this->faker->numerify('####'),
            'QueueUrl' => $this->faker->url,
            'BucketName' => $this->faker->word,
            'DBSecurityGroupId' => Str::random(),
            'ContainersSecurityGroupId' => Str::random(),
            'CacheSecurityGroupId' => Str::random(),
            'ArtisanTaskDefinitionArn' => Str::random(),
            'Subnet1Id' => Str::random(),
        ], [
            'ArtisanTaskDefinitionArn' => Str::random(),
            'ContainerClusterArn' => Str::random(),
        ]);
        $cloudformation->shouldReceive('updateStack')->andReturn();

        $existing_parameters = [
            $this->faker->word,
            $this->faker->word,
        ];

        $domain = $this->faker->domainName;

        $ssm = $this->mockLaraSurfSsmClient();
        $ssm->shouldReceive('listParameters')->andReturn($existing_parameters);
        $ssm->shouldReceive('deleteParameter')->andReturn();
        $ssm->shouldReceive('putParameter')->andReturn();
        $ssm->shouldReceive('listParameterArns')->andReturn([
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:' . base64_encode(Str::random()),
            'APP_URL' => "https://$domain",
            'CACHE_DRIVER' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $this->faker->domainName,
            'DB_PORT' => $this->faker->numerify('####'),
            'DB_DATABASE' => $this->faker->word,
            'DB_USERNAME' => Str::random(),
            'DB_PASSWORD' => Str::random(),
            'LOG_CHANNEL' => 'errorlog',
            'QUEUE_CONNECTION' => 'sqs',
            'MAIL_MAILER' => 'ses',
            'AWS_DEFAULT_REGION' => 'us-east-1',
            'REDIS_HOST' => $this->faker->url,
            'REDIS_PORT' => $this->faker->numerify('####'),
            'SQS_QUEUE' => $this->faker->url,
            'AWS_BUCKET' => $this->faker->word,
        ]);

        $hosted_zone_id = Str::random();

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')->andReturn($hosted_zone_id);
        $route53->shouldReceive('upsertDnsRecords')->andReturn(Str::random());
        $route53->shouldReceive('waitForChange')->andReturn();

        $acm = $this->mockLaraSurfAcmClient();
        $acm->shouldReceive('requestCertificate')->andReturn([
            'dns_record' => (new DnsRecord())
                ->setType(DnsRecord::TYPE_CNAME)
                ->setValue(Str::random())
                ->setName(Str::random())
                ->setTtl(random_int(100, 1000)),
            'certificate_arn' => Str::random(),
        ]);
        $acm->shouldReceive('waitForPendingValidation')->andReturn();

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('createPrefixList')->times(2)->andReturn(Str::random());

        $database_name = $this->faker->word;

        $schema_creator = Mockery::mock('overload:' . SchemaCreator::class);
        $schema_creator->shouldReceive('createSchema')->andReturn($database_name);

        $ecs = $this->mockLaraSurfEcsClient();
        $ecs->shouldReceive('runTask')->andReturn(Str::random());
        $ecs->shouldReceive('waitForTaskFinish')->andReturn();

        $this->artisan('larasurf:cloud-stacks create --environment production')
            ->expectsOutput('Checking if application and webserver images exist...')
            ->expectsOutput('Checking if stack exists...')
            ->expectsOutput("The following variables exist for the 'production' environment:")
            ->expectsOutput(implode(PHP_EOL, $existing_parameters))
            ->expectsQuestion('Are you sure you\'d like to delete these variables?', true)
            ->expectsOutput('Deleting cloud variables...')
            ->expectsQuestion('Database instance type?', 'db.t2.small')
            ->expectsOutput('Minimum database storage (GB): 20')
            ->expectsOutput('Maximum database storage (GB): 70368')
            ->expectsQuestion('Database storage (GB)?', '25')
            ->expectsQuestion('Cache node type?', 'cache.t2.micro')
            ->expectsQuestion('Task definition CPU?', '256')
            ->expectsQuestion('Task definition memory?', '512')
            ->expectsQuestion('Auto Scaling target CPU percent?', '50')
            ->expectsQuestion('Auto Scaling scale out cooldown (seconds)?', '10')
            ->expectsQuestion('Auto Scaling scale in cooldown (seconds)?', '10')
            ->expectsQuestion('Fully qualified domain name?', $domain)
            ->expectsOutput('Finding hosted zone from domain...')
            ->expectsOutput("Hosted zone found with ID: $hosted_zone_id")
            ->expectsQuestion('Is there a preexisting ACM certificate you\'d like to use?', true)
            ->expectsQuestion('ACM certificate ARN?', 'arn:aws:acm:us-east-1:certificate/' . Str::random())
            ->expectsOutput('Creating prefix lists...')
            ->expectsOutput('Created database prefix list successfully')
            ->expectsOutput('Created application prefix list successfully')
            ->expectsOutput("Creating stack for 'production' environment...")
            ->expectsOutput('Stack creation completed successfully')
            ->expectsOutput('Creating database schema...')
            ->expectsOutput("Created database schema '$database_name' successfully")
            ->expectsOutput('Creating cloud variables...')
            ->expectsOutput('Successfully created cloud variable: APP_ENV')
            ->expectsOutput('Successfully created cloud variable: APP_KEY')
            ->expectsOutput('Successfully created cloud variable: APP_URL')
            ->expectsOutput('Successfully created cloud variable: CACHE_DRIVER')
            ->expectsOutput('Successfully created cloud variable: DB_CONNECTION')
            ->expectsOutput('Successfully created cloud variable: DB_HOST')
            ->expectsOutput('Successfully created cloud variable: DB_PORT')
            ->expectsOutput('Successfully created cloud variable: DB_DATABASE')
            ->expectsOutput('Successfully created cloud variable: DB_USERNAME')
            ->expectsOutput('Successfully created cloud variable: DB_PASSWORD')
            ->expectsOutput('Successfully created cloud variable: LOG_CHANNEL')
            ->expectsOutput('Successfully created cloud variable: QUEUE_CONNECTION')
            ->expectsOutput('Successfully created cloud variable: MAIL_MAILER')
            ->expectsOutput('Successfully created cloud variable: AWS_DEFAULT_REGION')
            ->expectsOutput('Successfully created cloud variable: REDIS_HOST')
            ->expectsOutput('Successfully created cloud variable: REDIS_PORT')
            ->expectsOutput('Successfully created cloud variable: SQS_QUEUE')
            ->expectsOutput('Successfully created cloud variable: AWS_BUCKET')
            ->expectsOutput('Waiting to list cloud variables...')
            ->expectsOutput('Updating stack with cloud variables...')
            ->expectsOutput('Stack update completed successfully')
            ->expectsOutput('Starting ECS task to run migrations...')
            ->expectsOutput('Started ECS task to run migrations successfully')
            ->expectsOutput("Visit https://$domain to see your application")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateNotOnCorrectBranch()
    {
        $this->createGitHead('stage');

        $this->artisan('larasurf:cloud-stacks create --environment production')
            ->expectsOutput('Must be on the \'main\' branch to create a stack for this environment')
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateNoCurrentCommit()
    {
        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        if (File::isDirectory(base_path('.git/refs/heads'))) {
            File::deleteDirectory(base_path('.git/refs/heads'));
        }

        $this->createGitHead('main');
        $this->createValidLaraSurfConfig('local-stage-production');

        $this->artisan('larasurf:cloud-stacks create --environment production')
            ->expectsOutput('Failed to find current commit, is this a git repository?')
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateImageDoesntExist()
    {
        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        $current_commit = Str::random();

        $this->createGitHead('main');
        $this->createValidLaraSurfConfig('local-stage-production');
        $this->createGitCurrentCommit('main', $current_commit);

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('imageTagExists')->andReturn(false);

        $image_tag = 'commit-' . $current_commit;
        $application_repo_name = "{$this->project_name}-{$this->project_id}/production/application";

        $this->artisan('larasurf:cloud-stacks create --environment production')
            ->expectsOutput('Checking if application and webserver images exist...')
            ->expectsOutput("Failed to find tag '$image_tag' in ECR repository '$application_repo_name'")
            ->expectsOutput('Is CircleCI finished building and publishing the images?')
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateStackExists()
    {
        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        $this->createGitHead('main');
        $this->createValidLaraSurfConfig('local-stage-production');
        $this->createGitCurrentCommit('main', Str::random());

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('imageTagExists')->andReturn(true);

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('templatePath')->andReturn(base_path('.cloudformation/infrastructure.yml'));
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');

        $this->artisan('larasurf:cloud-stacks create --environment production')
            ->expectsOutput('Checking if application and webserver images exist...')
            ->expectsOutput('Checking if stack exists...')
            ->expectsOutput('Stack already exists for \'production\' environment')
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateHostedZoneNotFound()
    {
        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        $this->createGitHead('main');
        $this->createValidLaraSurfConfig('local-stage-production');
        $this->createGitCurrentCommit('main', Str::random());

        $ecr = $this->mockLaraSurfEcrClient();
        $ecr->shouldReceive('imageTagExists')->andReturn(true);

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('templatePath')->andReturn(base_path('.cloudformation/infrastructure.yml'));
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);

        $existing_parameters = [
            $this->faker->word,
            $this->faker->word,
        ];

        $domain = $this->faker->domainName;

        $ssm = $this->mockLaraSurfSsmClient();
        $ssm->shouldReceive('listParameters')->andReturn($existing_parameters);
        $ssm->shouldReceive('deleteParameter')->andReturn();

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')->andReturn(false);

        $this->artisan('larasurf:cloud-stacks create --environment production')
            ->expectsOutput('Checking if application and webserver images exist...')
            ->expectsOutput('Checking if stack exists...')
            ->expectsOutput("The following variables exist for the 'production' environment:")
            ->expectsOutput(implode(PHP_EOL, $existing_parameters))
            ->expectsQuestion('Are you sure you\'d like to delete these variables?', true)
            ->expectsOutput('Deleting cloud variables...')
            ->expectsQuestion('Database instance type?', 'db.t2.small')
            ->expectsOutput('Minimum database storage (GB): 20')
            ->expectsOutput('Maximum database storage (GB): 70368')
            ->expectsQuestion('Database storage (GB)?', '25')
            ->expectsQuestion('Cache node type?', 'cache.t2.micro')
            ->expectsQuestion('Task definition CPU?', '256')
            ->expectsQuestion('Task definition memory?', '512')
            ->expectsQuestion('Auto Scaling target CPU percent?', '50')
            ->expectsQuestion('Auto Scaling scale out cooldown (seconds)?', '10')
            ->expectsQuestion('Auto Scaling scale in cooldown (seconds)?', '10')
            ->expectsQuestion('Fully qualified domain name?', $domain)
            ->expectsOutput('Finding hosted zone from domain...')
            ->expectsOutput("Hosted zone for domain '$domain' could not be found")
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testUpdateNone()
    {
        Mockery::getConfiguration()->setConstantsMap([
            CloudFormationClient::class => [
                'STACK_STATUS_UPDATE_COMPLETE' => 'UPDATE_COMPLETE',
            ]
        ]);

        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('templatePath')->andReturn(base_path('.cloudformation/infrastructure.yml'));
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('updateStack')->andReturn();
        $cloudformation->shouldReceive('waitForStackInfoPanel')->andReturn([
            'success' => true,
            'status' => 'UPDATE_COMPLETE',
        ]);

        $ssm = $this->mockLaraSurfSsmClient();
        $ssm->shouldReceive('listParameterArns')->andReturn([
            Str::random() => Str::random(),
            Str::random() => Str::random(),
        ]);

        $this->artisan('larasurf:cloud-stacks update --environment production')
            ->expectsChoice('Which options would you like to change?', ['(None)'], [
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
            ])
            ->expectsOutput('Gathering cloud variables...')
            ->expectsOutput('Updating stack...')
            ->expectsOutput('Stack update completed successfully')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testUpdateAll()
    {
        Mockery::getConfiguration()->setConstantsMap([
            AcmClient::class => [
                'VALIDATION_METHOD_DNS' => 'DNS',
            ],
            CloudFormationClient::class => [
                'STACK_STATUS_UPDATE_COMPLETE' => 'UPDATE_COMPLETE',
            ]
        ]);

        if (!File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::put(base_path('.cloudformation/infrastructure.yml'), Str::random());
        }

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('templatePath')->andReturn(base_path('.cloudformation/infrastructure.yml'));
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('updateStack')->andReturn();
        $cloudformation->shouldReceive('waitForStackInfoPanel')->andReturn([
            'success' => true,
            'status' => 'UPDATE_COMPLETE',
        ]);

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')->andReturn(Str::random());
        $route53->shouldReceive('upsertDnsRecords')->andReturn(Str::random());
        $route53->shouldReceive('waitForChange')->andReturn();

        $acm = $this->mockLaraSurfAcmClient();
        $acm->shouldReceive('requestCertificate')->andReturn([
            'dns_record' => (new DnsRecord())
                ->setType(DnsRecord::TYPE_CNAME)
                ->setValue(Str::random())
                ->setName(Str::random())
                ->setTtl(random_int(100, 1000)),
            'certificate_arn' => Str::random(),
        ]);
        $acm->shouldReceive('waitForPendingValidation')->andReturn();

        $ssm = $this->mockLaraSurfSsmClient();
        $ssm->shouldReceive('listParameterArns')->andReturn([
            Str::random() => Str::random(),
            Str::random() => Str::random(),
        ]);

        $domain = $this->faker->domainName;

        $this->artisan('larasurf:cloud-stacks update --environment production')
            ->expectsChoice('Which options would you like to change?', [
                'Domain + ACM certificate ARN',
                'Database instance type',
                'Database storage size',
                'Cache node type',
                'Task definition CPU + Memory',
                'AutoScaling target CPU percent',
                'AutoScaling scale out cooldown',
                'AutoScaling scale in cooldown',
            ], [
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
            ])
            ->expectsQuestion('Fully qualified domain name?', $domain)
            ->expectsQuestion('Is there a preexisting ACM certificate you\'d like to use?', false)
            ->expectsOutput('Creating ACM certificate...')
            ->expectsOutput('Verifying ACM certificate via DNS record...')
            ->expectsOutput("Verified ACM certificate for domain '$domain' successfully")
            ->expectsQuestion('Database instance type?', 'db.t2.small')
            ->expectsOutput('Minimum database storage (GB): 20')
            ->expectsOutput('Maximum database storage (GB): 70368')
            ->expectsQuestion('Database storage (GB)?', '25')
            ->expectsQuestion('Cache node type?', 'cache.t2.micro')
            ->expectsQuestion('Task definition CPU?', '256')
            ->expectsQuestion('Task definition memory?', '512')
            ->expectsQuestion('Auto Scaling target CPU percent?', '50')
            ->expectsQuestion('Auto Scaling scale out cooldown (seconds)?', '10')
            ->expectsQuestion('Auto Scaling scale in cooldown (seconds)?', '10')
            ->expectsOutput('Gathering cloud variables...')
            ->expectsOutput('Updating stack...')
            ->expectsOutput('Stack update completed successfully')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testUpdateStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('templatePath')->andReturn(base_path('.cloudformation/infrastructure.yml'));
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);

        $this->artisan('larasurf:cloud-stacks update --environment production')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDelete()
    {
        Mockery::getConfiguration()->setConstantsMap([
            CloudFormationClient::class => [
                'STACK_STATUS_DELETED' => 'DELETED',
            ]
        ]);

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackOutput')->andReturn([
            'DBId' => Str::random(),
            'DBAdminAccessPrefixListId' => Str::random(),
            'AppAccessPrefixListId' => Str::random(),
        ]);
        $cloudformation->shouldReceive('stackStatus')->andReturn('UPDATE_COMPLETE');
        $cloudformation->shouldReceive('deleteStack')->andReturn();
        $cloudformation->shouldReceive('waitForStackInfoPanel')->andReturn([
            'success' => true,
            'status' => 'DELETED',
        ]);

        $rds = $this->mockLaraSurfRdsClient();
        $rds->shouldReceive('checkDeletionProtection')->andReturn(true);
        $rds->shouldReceive('modifyDeletionProtection')->andReturn();

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('deletePrefixList')->times(2)->andReturn(true);

        $this->artisan('larasurf:cloud-stacks delete --environment production')
            ->expectsConfirmation("Are you sure you want to delete the stack for the 'production' environment?", 'yes')
            ->expectsOutput('Getting stack outputs...')
            ->expectsOutput('Checking database for deletion protection...')
            ->expectsOutput("Deletion protection is enabled for the 'production' environment's database")
            ->expectsConfirmation('Would you like to disable deletion protection and proceed?', 'yes')
            ->expectsOutput('Disabling database deletion protection...')
            ->expectsOutput('Deletion protection disabled successfully')
            ->expectsOutput('Deleting prefix lists...')
            ->expectsOutput('Deleted database prefix list successfully')
            ->expectsOutput('Deleted application prefix list successfully')
            ->expectsOutput('Deleting stack...')
            ->expectsOutput('Stack deletion completed successfully')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDeleteNoDatabaseProtection()
    {
        Mockery::getConfiguration()->setConstantsMap([
            CloudFormationClient::class => [
                'STACK_STATUS_DELETED' => 'DELETED',
            ]
        ]);

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackOutput')->andReturn([
            'DBId' => Str::random(),
            'DBAdminAccessPrefixListId' => Str::random(),
            'AppAccessPrefixListId' => Str::random(),
        ]);
        $cloudformation->shouldReceive('stackStatus')->andReturn('UPDATE_COMPLETE');
        $cloudformation->shouldReceive('deleteStack')->andReturn();
        $cloudformation->shouldReceive('waitForStackInfoPanel')->andReturn([
            'success' => true,
            'status' => 'DELETED',
        ]);

        $rds = $this->mockLaraSurfRdsClient();
        $rds->shouldReceive('checkDeletionProtection')->andReturn(false);

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('deletePrefixList')->times(2)->andReturn(true);

        $this->artisan('larasurf:cloud-stacks delete --environment production')
            ->expectsConfirmation("Are you sure you want to delete the stack for the 'production' environment?", 'yes')
            ->expectsOutput('Getting stack outputs...')
            ->expectsOutput('Checking database for deletion protection...')
            ->expectsOutput('Deleting prefix lists...')
            ->expectsOutput('Deleted database prefix list successfully')
            ->expectsOutput('Deleted application prefix list successfully')
            ->expectsOutput('Deleting stack...')
            ->expectsOutput('Stack deletion completed successfully')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDeleteStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);

        $this->artisan('larasurf:cloud-stacks delete --environment production')
            ->expectsQuestion("Are you sure you want to delete the stack for the 'production' environment?", true)
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWait()
    {
        Mockery::getConfiguration()->setConstantsMap([
            CloudFormationClient::class => [
                'STACK_STATUS_UPDATE_COMPLETE' => 'UPDATE_COMPLETE',
            ]
        ]);

        $status = 'UPDATE_COMPLETE';

        $this->mockLaraSurfCloudFormationClient()->shouldReceive('waitForStackInfoPanel')->andReturn([
            'success' => true,
            'status' => $status,
        ]);

        $this->artisan('larasurf:cloud-stacks wait --environment production')
            ->expectsOutput("Stack operation finished with status: $status")
            ->assertExitCode(0);
    }
}
