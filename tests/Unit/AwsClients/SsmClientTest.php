<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\Tests\TestCase;

class SsmClientTest extends TestCase
{
    public function testParameterExists()
    {
        $name = $this->faker->word;
        $value = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => [
                    [
                        'Name' => $this->ssmParameterPath($name),
                        'Value' => $value,
                    ]
                ]
            ]);

        $this->assertTrue($this->ssmClient()->parameterExists($name));
    }

    public function testParameterExistsDoesntExist()
    {
        $name = $this->faker->word;
        $value = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => [
                    [
                        'Name' => $this->ssmParameterPath($name),
                        'Value' => $value,
                    ]
                ]
            ]);

        $this->assertFalse($this->ssmClient()->parameterExists(Str::random()));
    }

    public function testGetParameter()
    {
        $name = $this->faker->word;
        $value = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParameter')
            ->once()
            ->andReturn([
                'Parameter' => [
                    'Name' => $this->ssmParameterPath($name),
                    'Value' => $value,
                ]
            ]);

        $this->assertEquals($value, $this->ssmClient()->getParameter($name));
    }

    public function testGetParameterDoesntExist()
    {
        $this->mockAwsSsmClient()
            ->shouldReceive('getParameter')
            ->once()
            ->andReturn([
                'Parameters' => [
                    'Parameter' => [
                        'Name' => $this->ssmParameterPath($this->faker->word),
                        'Value' => Str::random(),
                    ]
                ]
            ]);

        $this->assertFalse($this->ssmClient()->getParameter(Str::random()));
    }

    public function testPutParameterNew()
    {
        $name = $this->faker->word;
        $value = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => []
            ])
            ->shouldReceive('putParameter')
            ->once();

        $this->ssmClient()->putParameter($name, $value);
    }

    public function testPutParameterExisting()
    {
        $name = $this->faker->word;
        $value = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => [
                    [
                        'Name' => $this->ssmParameterPath($name),
                        'Value' => $value,
                    ]
                ]
            ])
            ->shouldReceive('putParameter')
            ->once();

        $this->ssmClient()->putParameter($name, Str::random());
    }

    public function testDeleteParameter()
    {
        $this->mockAwsSsmClient()
            ->shouldReceive('deleteParameter')
            ->once();

        $this->ssmClient()->deleteParameter($this->faker->word);
    }

    public function testListParametersDecrypt()
    {
        $name1 = $this->faker->word;
        $value1 = Str::random();
        $name2 = $this->faker->word;
        $value2 = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => [
                    [
                        'Name' => $this->ssmParameterPath($name1),
                        'Value' => $value1,
                    ],
                    [
                        'Name' => $this->ssmParameterPath($name2),
                        'Value' => $value2,
                    ],
                ]
            ]);

        $results = $this->ssmClient()->listParameters(true);

        $this->assertEquals([
            $this->ssmParameterPath($name1) => $value1,
            $this->ssmParameterPath($name2) => $value2,
        ], $results);
    }

    public function testListParametersNoDecrypt()
    {
        $name1 = $this->faker->word;
        $value1 = Str::random();
        $name2 = $this->faker->word;
        $value2 = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => [
                    [
                        'Name' => $this->ssmParameterPath($name1),
                        'Value' => $value1,
                    ],
                    [
                        'Name' => $this->ssmParameterPath($name2),
                        'Value' => $value2,
                    ],
                ]
            ]);

        $results = $this->ssmClient()->listParameters(false);

        $this->assertEquals([$this->ssmParameterPath($name1), $this->ssmParameterPath($name2)], $results);
    }

    public function testListParameterArnsAssoc()
    {
        $key1 = $this->faker->word;
        $arn1 = Str::random();
        $key2 = $this->faker->word;
        $arn2 = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => [
                    [
                        'Name' => $this->ssmParameterPath($key1),
                        'ARN' => $arn1,
                    ],
                    [
                        'Name' => $this->ssmParameterPath($key2),
                        'ARN' => $arn2,
                    ],
                ]
            ]);

        $results = $this->ssmClient()->listParameterArns(true);

        $this->assertEquals([
            $key1 => $arn1,
            $key2 => $arn2,
        ], $results);
    }

    public function testListParameterArnsArray()
    {
        $key1 = $this->faker->word;
        $arn1 = Str::random();
        $key2 = $this->faker->word;
        $arn2 = Str::random();

        $this->mockAwsSsmClient()
            ->shouldReceive('getParametersByPath')
            ->once()
            ->andReturn([
                'Parameters' => [
                    [
                        'Name' => $this->ssmParameterPath($key1),
                        'ARN' => $arn1,
                    ],
                    [
                        'Name' => $this->ssmParameterPath($key2),
                        'ARN' => $arn2,
                    ],
                ]
            ]);

        $results = $this->ssmClient()->listParameterArns(false);

        $this->assertEquals([$arn1, $arn2], $results);
    }

    protected function ssmParameterPath(string $name, string $environment = Cloud::ENVIRONMENT_PRODUCTION): string
    {
        return '/' . $this->project_name . '-' . $this->project_id . '/' . $environment . '/' . $name;
    }
}
