<?php

namespace LaraSurf\LaraSurf\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
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

    protected function createValidLaraSurfConfig(string $environments)
    {
        $json = [
            'project-name' => implode('-', $this->faker->words()),
            'project-id' => Str::random(),
            'aws-profile' => $this->faker->word,
        ];

        switch ($environments) {
            case 'local': {
                $json['environments'] = null;

                break;
            }
            case 'local-production': {
                $json['environments'] = [
                    'production' => [
                        'aws-region' => Arr::random(Cloud::AWS_REGIONS),
                    ],
                ];

                break;
            }
            case 'local-stage-production': {
                $json['environments'] = [
                    'stage' => [
                        'aws-region' => Arr::random(Cloud::AWS_REGIONS),
                    ],
                    'production' => [
                        'aws-region' => Arr::random(Cloud::AWS_REGIONS),
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
}
