<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudVarsTest extends TestCase
{
    public function testExists()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $this->mockLaraSurfSsmClient()->shouldReceive('getParameter')->once()->andReturn(Str::random());

        $key = strtoupper($this->faker->word);

        $this->artisan('larasurf:cloud-vars exists --environment production --key '. $key)
            ->expectsOutput("Variable '$key' exists in 'production' environment")
            ->assertExitCode(0);
    }

    public function testExistsDoesntExist()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $this->mockLaraSurfSsmClient()->shouldReceive('getParameter')->once()->andReturn(false);

        $key = strtoupper($this->faker->word);

        $this->artisan('larasurf:cloud-vars exists --environment production --key '. $key)
            ->expectsOutput("Variable '$key' does not exist in the 'production' environment")
            ->assertExitCode(1);
    }

    public function testGet()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $value = Str::random();

        $this->mockLaraSurfSsmClient()->shouldReceive('getParameter')->once()->andReturn($value);

        $key = strtoupper($this->faker->word);

        $this->artisan('larasurf:cloud-vars get --environment production --key '. $key)
            ->expectsOutput($value)
            ->assertExitCode(0);
    }

    public function testGetDoesntExist()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $this->mockLaraSurfSsmClient()->shouldReceive('getParameter')->once()->andReturn(false);

        $key = strtoupper($this->faker->word);

        $this->artisan('larasurf:cloud-vars get --environment production --key '. $key)
            ->expectsOutput("Variable '$key' does not exist in the 'production' environment")
            ->assertExitCode(1);
    }

    public function testPut()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $key = strtoupper($this->faker->word);

        $this->mockLaraSurfSsmClient()->shouldReceive('putParameter')->once()->andReturn();

        $this->artisan('larasurf:cloud-vars put --environment production --key '. $key . ' --value ' . Str::random())
            ->expectsOutput("Variable '$key' set in the 'production' environment successfully")
            ->assertExitCode(0);
    }

    public function testDelete()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $key = strtoupper($this->faker->word);

        $ssm = $this->mockLaraSurfSsmClient();
        $ssm->shouldReceive('getParameter')->once()->andReturn(Str::random());
        $ssm->shouldReceive('deleteParameter')->once()->andReturn();

        $this->artisan('larasurf:cloud-vars delete --environment production --key '. $key)
            ->expectsOutput("Variable '$key' in the 'production' environment deleted successfully")
            ->assertExitCode(0);
    }

    public function testDeleteDoesntExist()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $key = strtoupper($this->faker->word);

        $ssm = $this->mockLaraSurfSsmClient();
        $ssm->shouldReceive('getParameter')->once()->andReturn(false);

        $this->artisan('larasurf:cloud-vars delete --environment production --key '. $key)
            ->expectsOutput("Variable '$key' does not exist in the 'production' environment")
            ->assertExitCode(1);
    }

    public function testList()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $key1 = Str::random();
        $key2 = Str::random();

        $this->mockLaraSurfSsmClient()->shouldReceive('listParameters')->once()->andReturn([
            $key1,
            $key2
        ]);

        $this->artisan('larasurf:cloud-vars list --environment production')
            ->expectsOutput($key1)
            ->expectsOutput($key2)
            ->assertExitCode(0);
    }

    public function testListValues()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $key1 = Str::random();
        $key2 = Str::random();
        $value1 = Str::random();
        $value2 = Str::random();

        $this->mockLaraSurfSsmClient()->shouldReceive('listParameters')->once()->andReturn([
            $key1 => $value1,
            $key2 => $value2,
        ]);

        $this->artisan('larasurf:cloud-vars list --environment production --values')
            ->expectsOutput("$key1: $value1")
            ->expectsOutput("$key2: $value2")
            ->assertExitCode(0);
    }
}
