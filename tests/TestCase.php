<?php

namespace LaraSurf\LaraSurf\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\Ec2Client;
use LaraSurf\LaraSurf\AwsClients\Route53Client;
use LaraSurf\LaraSurf\AwsClients\SesClient;
use LaraSurf\LaraSurf\AwsClients\SsmClient;
use LaraSurf\LaraSurf\Constants\Cloud;
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
        $this->project_id = Str::random();
        $this->aws_profile = $this->faker->word;
        $this->aws_region = Arr::random(Cloud::AWS_REGIONS);
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
        File::makeDirectory($this->cloudformation_directory_path);
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
}
