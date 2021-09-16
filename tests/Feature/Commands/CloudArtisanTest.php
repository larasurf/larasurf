<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudArtisanTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHandle()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        $logs = [
            Str::random(),
            Str::random(),
        ];

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->andReturn([
            'ContainerClusterArn' => Str::random(),
            'DBSecurityGroupId' => Str::random(),
            'CacheSecurityGroupId' => Str::random(),
            'ContainersSecurityGroupId' => Str::random(),
            'Subnet1Id' => Str::random(),
            'ArtisanTaskDefinitionArn' => Str::random(),
        ]);
        $cloudformation->shouldReceive('stackOutput')->andReturn(Str::random());

        $ecs = $this->mockLaraSurfEcsClient();
        $ecs->shouldReceive('runTask')->andReturn(Str::random());
        $ecs->shouldReceive('waitForTaskFinish')->andReturn();

        $this->mockLaraSurfCloudWatchLogsClient()
            ->shouldReceive('listLogStream')
            ->andReturn($logs);

        $this->artisan('larasurf:cloud-artisan "test-command" --environment production')
            ->expectsOutput('Running ECS task...')
            ->expectsOutput('Task output:')
            ->expectsOutput(implode(PHP_EOL, $logs));
    }
}
