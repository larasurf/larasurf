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
            ->once()
            ->andReturn();

        $this->ecsClient()->runTask(Str::random(), [], [], [], Str::random());
    }

    public function testStopTask()
    {
        $this->mockAwsEcsClient()
            ->shouldReceive('stopTask')
            ->once()
            ->andReturn();

        $this->ecsClient()->stopTask(Str::random(), Str::random());
    }

    public function testListRunningTasks()
    {
        $arn1 = Str::random();
        $arn2 = Str::random();

        $this->mockAwsEcsClient()
            ->shouldReceive('listTasks')
            ->once()
            ->andReturn([
                'taskArns' => [$arn1, $arn2],
            ]);

        $results = $this->ecsClient()->listRunningTasks(Str::random());

        $this->assertEquals([$arn1, $arn2], $results);
    }

    public function testExecuteCommand()
    {
        $this->mockAwsEcsClient()
            ->shouldReceive('executeCommand')
            ->once()
            ->andReturn();

        $this->ecsClient()->executeCommand(Str::random(), Str::random(), $this->faker->word, $this->faker->word, $this->faker->boolean);
    }

    public function testWaitForTaskFinish()
    {
        $this->mockAwsEcsClient()
            ->shouldReceive('describeTasks')
            ->once()
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
