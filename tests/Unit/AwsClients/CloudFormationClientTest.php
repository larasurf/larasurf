<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\Exceptions\AwsClients\EnvironmentNotSetException;
use LaraSurf\LaraSurf\Tests\TestCase;
use League\Flysystem\FileNotFoundException;
use Mockery;

class CloudFormationClientTest extends TestCase
{
    protected function tearDown(): void
    {
        if (File::exists($this->cloudformation_template_path)) {
            File::delete($this->cloudformation_template_path);
        }

        if (File::isDirectory($this->cloudformation_directory_path)) {
            File::deleteDirectory($this->cloudformation_directory_path);
        }

        parent::tearDown();
    }

    public function testCreateStack()
    {
        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('createStack');

        $this->createMockCloudformationTemplate();

        $this->cloudFormationClient()->createStack(
            $this->faker->domainName,
            Str::random(),
            random_int(20, 100),
            Arr::random(Cloud::DB_INSTANCE_TYPES),
            Str::random(),
            Str::random()
        );
    }

    public function testCreateStackEnvironmentNotSet()
    {
        $this->expectException(EnvironmentNotSetException::class);

        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class);

        $cloudformation = new CloudFormationClient(
            $this->projectName(),
            $this->projectId(),
            $this->awsProfile(),
            $this->awsRegion()
        );

        $cloudformation->createStack(
            $this->faker->domainName,
            Str::random(),
            random_int(20, 100),
            Arr::random(Cloud::DB_INSTANCE_TYPES),
            Str::random(),
            Str::random()
        );
    }

    public function testCreateStackNoTemplate()
    {
        $this->expectException(FileNotFoundException::class);

        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('createStack');

        $this->cloudFormationClient()->createStack(
            $this->faker->domainName,
            Str::random(),
            random_int(20, 100),
            Arr::random(Cloud::DB_INSTANCE_TYPES),
            Str::random(),
            Str::random()
        );
    }

    public function testUpdateStack()
    {
        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('updateStack');

        $this->createMockCloudformationTemplate();

        $this->cloudFormationClient()->updateStack(
            $this->faker->domainName,
            Str::random(),
            random_int(20, 100),
            Arr::random(Cloud::DB_INSTANCE_TYPES)
        );
    }

    public function testWaitForStackUpdateSuccess()
    {
        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
            ->andReturn([
                'Stacks' => [
                    [
                        'StackStatus' => 'CREATE_COMPLETE',
                    ],
                ],
            ]);

        $result = $this->cloudFormationClient()->waitForStackUpdate();

        $this->assertTrue($result['success']);
        $this->assertEquals('CREATE_COMPLETE', $result['status']);
    }

    public function testWaitForStackUpdateFailure()
    {
        $status = $this->faker->word;

        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
            ->andReturn([
                'Stacks' => [
                    [
                        'StackStatus' => $status,
                    ],
                ],
            ]);

        $result = $this->cloudFormationClient()->waitForStackUpdate();

        $this->assertFalse($result['success']);
        $this->assertEquals($status, $result['status']);
    }

    public function testStackStatus()
    {
        $status = $this->faker->word;

        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
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
        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
            ->andReturn([
                'Stacks' => [],
            ]);

        $this->assertFalse($this->cloudFormationClient()->stackStatus());
    }

    public function testStackOutputSingle()
    {
        $key = $this->faker->word;
        $value = Str::random();

        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
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
        $key1 = $this->faker->word;
        $value1 = Str::random();
        $key2 = $this->faker->word;
        $value2 = Str::random();

        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
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
        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
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
        Mockery::mock('overload:' . \Aws\CloudFormation\CloudFormationClient::class)
            ->shouldReceive('describeStacks')
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
