<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Config;
use LaraSurf\LaraSurf\Tests\TestCase;

class ConfigTest extends TestCase
{
    public function testGet()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $this->artisan('larasurf:config get project-name')
            ->expectsOutput($this->project_name)
            ->assertExitCode(0);
    }

    public function testGetDoesntExist()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $key = $this->faker->word;

        $this->artisan('larasurf:config get ' . $key)
            ->expectsOutput("Key '$key' not found in 'larasurf.json'")
            ->assertExitCode(1);
    }

    public function testGetDotNotation()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $this->artisan('larasurf:config get environments.production.aws-region')
            ->expectsOutput($this->aws_region)
            ->assertExitCode(0);
    }

    public function testSet()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $value = strtolower(Str::random());

        $this->artisan('larasurf:config set project-name ' . $value)
            ->expectsOutput("File 'larasurf.json' updated successfully")
            ->assertExitCode(0);

        $config = (new Config())->load();
        $this->assertEquals($value, $config->get('project-name'));
    }

    public function testSetDotNotation()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $value = 'us-east-1';

        $this->artisan('larasurf:config set environments.production.aws-region ' . $value)
            ->expectsOutput("File 'larasurf.json' updated successfully")
            ->assertExitCode(0);

        $config = (new Config())->load();
        $this->assertEquals($value, $config->get('environments.production.aws-region'));
    }
}
