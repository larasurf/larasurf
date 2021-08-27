<?php

namespace LaraSurf\LaraSurf\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\Client;
use LaraSurf\LaraSurf\Constants\Cloud;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithFaker;

    const ENVIRONMENT_CONFIGS = [
        'local',
        'local-production',
        'local-stage-production',
    ];

    protected string $config_path;

    public function setUp(): void
    {
        parent::setUp();

        $this->config_path = base_path('larasurf.json');
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

    protected function awsClient(string $type, string $environment = Cloud::ENVIRONMENT_PRODUCTION): Client
    {
        return new $type(
            $this->projectName(),
            $this->projectId(),
            $this->awsProfile(),
            $this->awsRegion(),
            $environment
        );
    }

    protected function acmClient(string $environment = Cloud::ENVIRONMENT_PRODUCTION): AcmClient
    {
        return $this->awsClient(AcmClient::class, $environment);
    }
}
