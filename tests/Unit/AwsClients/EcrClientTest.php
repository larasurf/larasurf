<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\Tests\TestCase;

class EcrClientTest extends TestCase
{
    public function testRepositoryUri()
    {
        $uri = $this->faker->url;

        $this->mockAwsEcrClient()
            ->shouldReceive('describeRepositories')
            ->once()
            ->andReturn([
                'repositories' => [
                    [
                        'repositoryUri' => $uri,
                    ],
                ],
            ]);

        $this->assertEquals($uri, $this->ecrClient()->repositoryUri(Str::random()));
    }

    public function testCreateRepository()
    {
        $uri = $this->faker->url;

        $this->mockAwsEcrClient()
            ->shouldReceive('createRepository')
            ->once()
            ->andReturn([
                'repository' => [
                    'repositoryUri' => $uri,
                ]
            ]);

        $this->assertEquals($uri, $this->ecrClient()->createRepository(Str::random()));
    }

    public function testDeleteRepository()
    {
        $this->mockAwsEcrClient()
            ->shouldReceive('deleteRepository')
            ->once();

        $this->ecrClient()->deleteRepository(Str::random());
    }
}
