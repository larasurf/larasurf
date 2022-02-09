<?php

namespace LaraSurf\LaraSurf\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\CloudWatchLogsClient;
use LaraSurf\LaraSurf\AwsClients\Ec2Client;
use LaraSurf\LaraSurf\AwsClients\EcrClient;
use LaraSurf\LaraSurf\AwsClients\EcsClient;
use LaraSurf\LaraSurf\AwsClients\IamClient;
use LaraSurf\LaraSurf\AwsClients\RdsClient;
use LaraSurf\LaraSurf\AwsClients\Route53Client;
use LaraSurf\LaraSurf\AwsClients\SesClient;
use LaraSurf\LaraSurf\AwsClients\SsmClient;
use LaraSurf\LaraSurf\CircleCI\Client;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\LaraSurfServiceProvider;
use Mockery;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithFaker;

    const ENVIRONMENT_CONFIGS = [
        'local',
        'local-production',
        'local-stage-production',
    ];

    protected string $config_path;
    protected string $cloudformation_template_path;
    protected string $cloudformation_directory_path;
    protected string $project_name;
    protected string $project_id;
    protected string $aws_profile;
    protected string $aws_region;

    public function setUp(): void
    {
        parent::setUp();

        $this->config_path = base_path('larasurf.json');
        $this->cloudformation_template_path = base_path('.cloudformation/infrastructure.yml');
        $this->cloudformation_directory_path = base_path('.cloudformation');
        $this->project_name = implode('-', $this->faker->words());
        $this->project_id = $this->faker->numerify('######');
        $this->aws_profile = $this->faker->word;
        $this->aws_region = Arr::random(Cloud::AWS_REGIONS);

        if (!File::isDirectory(base_path('.circleci'))) {
            File::makeDirectory(base_path('.circleci'));
        }
    }

    protected function getPackageProviders($app)
    {
        return [LaraSurfServiceProvider::class];
    }

    protected function createGitConfig(string $project_name)
    {
        if (!File::isDirectory(base_path('.git'))) {
            File::makeDirectory(base_path('.git'));
        }

        $contents = <<<EOF
[core]
        repositoryformatversion = 0
        filemode = false
        bare = false
        logallrefupdates = true
        ignorecase = true
[remote "origin"]
        url = git@github.com:$project_name.git
        fetch = +refs/heads/*:refs/remotes/origin/*
EOF;

        File::put(base_path('.git/config'), $contents);
    }

    protected function createGitHead(string $branch)
    {
        if (!File::isDirectory(base_path('.git'))) {
            File::makeDirectory(base_path('.git'));
        }

        $contents = "ref: refs/heads/$branch";

        File::put(base_path('.git/HEAD'), $contents);
    }

    protected function createGitCurrentCommit(string $branch, string $commit)
    {
        if (!File::isDirectory(base_path('.git/refs/heads'))) {
            File::makeDirectory(base_path('.git/refs/heads'), 0755, true);
        }

        File::put(base_path(".git/refs/heads/$branch"), $commit);
    }

    protected function createCircleCIApiKey(string $key)
    {
        File::put(base_path('.circleci/api-key.txt'), $key);
    }

    protected function createValidLaraSurfConfig(string $environments)
    {
        $json = [
            'project-name' => $this->project_name,
            'project-id' => $this->project_id,
            'aws-profile' => $this->aws_profile,
        ];

        switch ($environments) {
            case 'local': {
                $json['environments'] = null;

                break;
            }
            case 'local-production': {
                $json['environments'] = [
                    'production' => [
                        'aws-region' => $this->aws_region,
                    ],
                ];

                break;
            }
            case 'local-stage-production': {
                $json['environments'] = [
                    'stage' => [
                        'aws-region' => $this->aws_region,
                    ],
                    'production' => [
                        'aws-region' => $this->aws_region,
                    ],
                ];

                break;
            }
            default: {
                throw new InvalidArgumentException("Invalid environments string '$environments'");
            }
        }

        File::put($this->config_path, json_encode($json, JSON_PRETTY_PRINT) . PHP_EOL);
    }

    protected function createMockCloudformationTemplate()
    {
        if (!File::isDirectory($this->cloudformation_directory_path)) {
            File::makeDirectory($this->cloudformation_directory_path);
        }

        File::put($this->cloudformation_template_path, Str::random());
    }

    protected function awsClient(string $type, string $environment = Cloud::ENVIRONMENT_PRODUCTION)
    {
        return new $type(
            $this->project_name,
            $this->project_id,
            $this->aws_profile,
            $this->aws_region,
            $environment
        );
    }

    protected function acmClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): AcmClient
    {
        return $this->awsClient(AcmClient::class, $environment);
    }

    protected function mockAwsAcmClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Acm\AcmClient::class);
    }

    protected function cloudFormationClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): CloudFormationClient
    {
        return $this->awsClient(CloudFormationClient::class, $environment);
    }

    protected function mockAwsCloudFormationClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class);
    }

    protected function ec2Client(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): Ec2Client
    {
        return $this->awsClient(Ec2Client::class, $environment);
    }

    protected function mockAwsEc2Client(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Ec2\Ec2Client::class);
    }

    protected function route53Client(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): Route53Client
    {
        return $this->awsClient(Route53Client::class, $environment);
    }

    protected function mockAwsRoute53Client(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Route53\Route53Client::class);
    }

    protected function sesClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): SesClient
    {
        return $this->awsClient(SesClient::class, $environment);
    }

    protected function mockAwsSesClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Ses\SesClient::class);
    }

    protected function mockAwsSesV2Client(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\SesV2\SesV2Client::class);
    }

    protected function ssmClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): SsmClient
    {
        return $this->awsClient(SsmClient::class, $environment);
    }

    protected function mockAwsSsmClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Ssm\SsmClient::class);
    }

    protected function rdsClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): RdsClient
    {
        return $this->awsClient(RdsClient::class, $environment);
    }

    protected function mockAwsRdsClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Rds\RdsClient::class);
    }

    protected function ecrClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): EcrClient
    {
        return $this->awsClient(EcrClient::class, $environment);
    }

    protected function mockAwsEcrClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Ecr\EcrClient::class);
    }

    protected function iamClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): IamClient
    {
        return $this->awsClient(IamClient::class, $environment);
    }

    protected function mockAwsIamClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Iam\IamClient::class);
    }

    protected function ecsClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): EcsClient
    {
        return $this->awsClient(EcsClient::class, $environment);
    }

    protected function mockAwsEcsClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\Ecs\EcsClient::class);
    }

    protected function cloudWatchLogsClient(?string $environment = Cloud::ENVIRONMENT_PRODUCTION): CloudWatchLogsClient
    {
        return $this->awsClient(CloudWatchLogsClient::class, $environment);
    }

    protected function mockAwsCloudWatchLogsClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . \Aws\CloudWatchLogs\CloudWatchLogsClient::class);
    }

    protected function mockLaraSurfCloudFormationClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . CloudFormationClient::class);
    }

    protected function mockLaraSurfRdsClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . RdsClient::class);
    }

    protected function mockLaraSurfEcsClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . EcsClient::class);
    }

    protected function mockLaraSurfSsmClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . SsmClient::class);
    }

    protected function mockLaraSurfCloudWatchLogsClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . CloudWatchLogsClient::class);
    }

    protected function mockLaraSurfRoute53Client(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . Route53Client::class);
    }

    protected function mockLaraSurfAcmClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . AcmClient::class);
    }

    protected function mockLaraSurfSesClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . SesClient::class);
    }

    protected function mockLaraSurfEcrClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . EcrClient::class);
    }

    protected function mockLaraSurfEc2Client(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . Ec2Client::class);
    }

    protected function mockLaraSurfIamClient(): Mockery\MockInterface
    {
        return Mockery::mock('overload:' . IamClient::class);
    }

    protected function mockCircleCI(): Mockery\MockInterface
    {
        $mock = $this->mock(Client::class);
        $mock->shouldReceive('configure')->andReturnSelf();

        return $mock;
    }
}
