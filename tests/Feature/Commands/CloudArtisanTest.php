<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudArtisanTest extends TestCase
{
    public function testHandle()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $logs = [
            Str::random(),
            Str::random(),
        ];

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn([
            'ContainerClusterArn' => Str::random(),
            'DBSecurityGroupId' => Str::random(),
            'CacheSecurityGroupId' => Str::random(),
            'ContainersSecurityGroupId' => Str::random(),
            'Subnet1Id' => Str::random(),
            'ArtisanTaskDefinitionArn' => Str::random(),
        ]);
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn(Str::random());

        $ecs = $this->mockLaraSurfEcsClient();
        $ecs->shouldReceive('runTask')->once()->andReturn(Str::random());
        $ecs->shouldReceive('waitForTaskFinish')->once()->andReturn();

        $this->mockLaraSurfCloudWatchLogsClient()
            ->shouldReceive('listLogStream')
            ->once()
            ->andReturn($logs);

        $this->artisan('larasurf:cloud-artisan "test-command" --environment production')
            ->expectsOutput('Running ECS task...')
            ->expectsOutput('Task output:')
            ->expectsOutput(implode(PHP_EOL, $logs));
    }
}
