<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudFormationClientTest extends TestCase
{
    public function testCreateStack()
    {
        $this->mockAwsCloudFormationClient()
            ->shouldReceive('createStack')
            ->once();

        $this->createMockCloudformationTemplate();

        $this->cloudFormationClient()->createStack(
            false,
            'test.' . $this->faker->domainName,
            $this->faker->domainName,
            Str::random(),
            Str::random(),
            random_int(20, 100),
            Arr::random(Cloud::DB_INSTANCE_TYPES),
            Str::random(),
            Str::random(),
            Arr::random(Cloud::CACHE_NODE_TYPES),
            Str::random(),
            Str::random(),
            Str::random(),
            Str::random(),
            Str::random(),
            Str::random(),
            random_int(1, 100),
            random_int(1, 100),
            random_int(1, 100),
            random_int(1, 5),
            random_int(5, 15),
            random_int(1, 5)
        );
    }

    public function testUpdateStack()
    {
        $this->mockAwsCloudFormationClient()
            ->shouldReceive('updateStack')
            ->once();

        $this->createMockCloudformationTemplate();

        $this->cloudFormationClient()->updateStack(
            true,
            [],
            'test.' . $this->faker->domainName,
            $this->faker->domainName,
            Str::random(),
            Str::random(),
            random_int(20, 100),
            Arr::random(Cloud::DB_INSTANCE_TYPES),
            Arr::random(Cloud::CACHE_NODE_TYPES)
        );
    }

    public function testWaitForStackUpdateSuccess()
    {
        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [
                    [
                        'StackStatus' => 'CREATE_COMPLETE',
                    ],
                ],
            ]);

        $result = $this->cloudFormationClient()->waitForStackInfoPanel('CREATE_COMPLETE');

        $this->assertTrue($result['success']);
        $this->assertEquals('CREATE_COMPLETE', $result['status']);
    }

    public function testWaitForStackUpdateFailure()
    {
        $status = $this->faker->word;

        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [
                    [
                        'StackStatus' => $status,
                    ],
                ],
            ]);

        $result = $this->cloudFormationClient()->waitForStackInfoPanel(Str::random());

        $this->assertFalse($result['success']);
        $this->assertEquals($status, $result['status']);
    }

    public function testStackStatus()
    {
        $status = $this->faker->word;

        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [
                    [
                        'StackStatus' => $status,
                    ],
                ],
            ]);

        $this->assertEquals($status, $this->cloudFormationClient()->stackStatus());
    }

    public function testStackStatusDoesntExist()
    {
        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [],
            ]);

        $this->assertFalse($this->cloudFormationClient()->stackStatus());
    }

    public function testStackOutputSingle()
    {
        $key = $this->faker->word;
        $value = Str::random();

        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [
                    [
                        'Outputs' => [
                            [
                                'OutputKey' => $key,
                                'OutputValue' => $value,
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertEquals($value, $this->cloudFormationClient()->stackOutput($key));
    }

    public function testStackOutputMultiple()
    {
        $key1 = Str::random();
        $value1 = Str::random();
        $key2 = Str::random();
        $value2 = Str::random();

        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [
                    [
                        'Outputs' => [
                            [
                                'OutputKey' => $key1,
                                'OutputValue' => $value1,
                            ],
                            [
                                'OutputKey' => $key2,
                                'OutputValue' => $value2,
                            ],
                        ],
                    ],
                ],
            ]);

        $outputs = $this->cloudFormationClient()->stackOutput([$key1, $key2]);

        $this->assertEquals($value1, $outputs[$key1]);
        $this->assertEquals($value2, $outputs[$key2]);
    }

    public function testStackOutputDoesntExistSingle()
    {
        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [
                    [
                        'Outputs' => [
                            [
                                'OutputKey' => $this->faker->word,
                                'OutputValue' => Str::random(),
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertFalse($this->cloudFormationClient()->stackOutput(Str::random()));
    }

    public function testStackOutputDoesntExistMultiple()
    {
        $this->mockAwsCloudFormationClient()
            ->shouldReceive('describeStacks')
            ->once()
            ->andReturn([
                'Stacks' => [
                    [
                        'Outputs' => [
                            [
                                'OutputKey' => $this->faker->word,
                                'OutputValue' => Str::random(),
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertEmpty($this->cloudFormationClient()->stackOutput([Str::random(), Str::random()]));
    }
}
