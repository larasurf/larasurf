<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class EcsClientTest extends TestCase
{
    public function testRunTask()
    {
        $this->mockAwsEcsClient()
            ->shouldReceive('runTask')
            ->andReturn();

        $this->ecsClient()->runTask(Str::random(), [], [], [], Str::random());
    }

    public function testWaitForTaskFinish()
    {
        $this->mockAwsEcsClient()
            ->shouldReceive('describeTasks')
            ->andReturn([
                'tasks' => [
                    [
                        'lastStatus' => 'STOPPED',
                    ],
                ],
            ]);

        $this->ecsClient()->waitForTaskFinish(Str::random(), Str::random());
    }
}
