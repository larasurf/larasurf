<?php

namespace LaraSurf\LaraSurf\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\Client;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\Ec2Client;
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

    public function setUp(): void
    {
        parent::setUp();

        $this->config_path = base_path('larasurf.json');
        $this->cloudformation_template_path = base_path('.cloudformation/infrastructure.yml');
        $this->cloudformation_directory_path = base_path('.cloudformation');
    }

    protected function projectName(): string
    {
        return implode('-', $this->faker->words());
    }

    protected function projectId(): string
    {
        return Str::random(16);
    }

    protected function awsProfile(): string
    {
        return $this->faker->word;
    }

    protected function awsRegion(): string
    {
        return  Arr::random(Cloud::AWS_REGIONS);
    }

    protected function createValidLaraSurfConfig(string $environments)
    {
        $json = [
            'project-name' => $this->projectName(),
            'project-id' => $this->projectId(),
            'aws-profile' => $this->awsProfile(),
        ];

        switch ($environments) {
            case 'local': {
                $json['environments'] = null;

                break;
            }
            case 'local-production': {
                $json['environments'] = [
                    'production' => [
                        'aws-region' => $this->awsRegion(),
                    ],
                ];

                break;
            }
            case 'local-stage-production': {
                $json['environments'] = [
                    'stage' => [
                        'aws-region' => $this->awsRegion(),
                    ],
                    'production' => [
                        'aws-region' => $this->awsRegion(),
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
            $this->projectName(),
            $this->projectId(),
            $this->awsProfile(),
            $this->awsRegion(),
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
}
