<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudWatchLogsClientTest extends TestCase
{
    public function testListLogStream()
    {
        $next_token = Str::random();
        $message1 = $this->faker->sentence;
        $message2 = $this->faker->sentence;

        $this->mockAwsCloudWatchLogsClient()
            ->shouldReceive('getLogEvents')
            ->once()
            ->andReturn([
                'events' => [
                    [
                        'message' => $message1,
                    ],
                    [
                        'message' => $message2,
                    ],
                ],
                'nextForwardToken' => $next_token,
            ]);

        $logs = $this->cloudWatchLogsClient()->listLogStream(Str::random(), Str::random());

        $this->assertEquals([
            $message1,
            $message2,
        ], $logs);
    }
}
