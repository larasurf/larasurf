<?php

namespace LaraSurf\LaraSurf\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\Config;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigKeyException;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigValueException;
use LaraSurf\LaraSurf\Tests\TestCase;

class LaraSurfConfigTest extends TestCase
{
    public function testExistsLocal()
    {
        $this->createValidLaraSurfConfig('local');

        $config = new Config('larasurf.json');

        foreach ([
            'project-name',
            'project-id',
            'aws-profile',
                 ] as $key) {
            $this->assertTrue($config->exists($key));
        }

        $this->assertFalse($config->exists('environments.stage.aws-region'));
        $this->assertFalse($config->exists('environments.production.aws-region'));
    }

    public function testExistsLocalProduction()
    {
        $this->createValidLaraSurfConfig('local-production');

        $config = new Config('larasurf.json');

        foreach ([
            'project-name',
            'project-id',
            'aws-profile',
            'environments.production.aws-region',
                 ] as $key) {
            $this->assertTrue($config->exists($key), "Missing key '$key'");
        }

        $this->assertFalse($config->exists('environments.stage.aws-region'));
    }

    public function testExistsLocalStageProduction()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $config = new Config('larasurf.json');

        foreach ([
            'project-name',
            'project-id',
            'aws-profile',
            'environments.stage.aws-region',
            'environments.production.aws-region',
                 ] as $key) {
            $this->assertTrue($config->exists($key), "Missing key $key");
        }
    }

    public function testInvalidConfigurationKey()
    {
        $this->expectException(InvalidConfigKeyException::class);

        $this->createValidLaraSurfConfig(Arr::random(self::ENVIRONMENT_CONFIGS));

        $config = new Config('larasurf.json');
        $config->exists($this->faker->word);
    }

    public function testInvalidProjectName()
    {
        $this->expectException(InvalidConfigValueException::class);

        $this->createValidLaraSurfConfig('local-stage-production');

        $config = new Config('larasurf.json');

        $config->set('project-name', strtoupper($this->faker->word));
    }

    public function testInvalidProjectId()
    {
        $this->expectException(InvalidConfigValueException::class);

        $this->createValidLaraSurfConfig('local-stage-production');

        $config = new Config('larasurf.json');

        $config->set('project-id', $this->faker->word);
    }

    public function testInvalidAwsProfile()
    {
        $this->expectException(InvalidConfigValueException::class);

        $this->createValidLaraSurfConfig('local-stage-production');

        $config = new Config('larasurf.json');

        $config->set('aws-profile', $this->faker->word . implode('', Arr::random([
                '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '=', '{', '}', '[', ']', '|', '\\', '/', ',', '.', '<', '>', '?',
            ], 10)));
    }

    public function testInvalidStageAwsRegion()
    {
        $this->expectException(InvalidConfigValueException::class);

        $this->createValidLaraSurfConfig('local-stage-production');

        $config = new Config('larasurf.json');

        $config->set('environments.stage.aws-region', $this->faker->word);
    }

    public function testInvalidProductionAwsRegion()
    {
        $this->expectException(InvalidConfigValueException::class);

        $this->createValidLaraSurfConfig('local-stage-production');

        $config = new Config('larasurf.json');

        $config->set('environments.production.aws-region', $this->faker->word);
    }
}
