<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class RdsClientTest extends TestCase
{
    public function testCheckDeletionProtection()
    {
        $this->mockAwsRdsClient()
            ->shouldReceive('describeDBInstances')
            ->once()
            ->andReturn([
                'DBInstances' => [
                    [
                        'DeletionProtection' => true,
                    ],
                ],
            ]);

        $this->assertTrue($this->rdsClient()->checkDeletionProtection(Str::random()));
    }

    public function testCheckDeletionProtectionDoesntExist()
    {
        $this->mockAwsRdsClient()
            ->shouldReceive('describeDBInstances')
            ->once()
            ->andReturn([
                'DBInstances' => [],
            ]);

        $this->assertFalse($this->rdsClient()->checkDeletionProtection(Str::random()));
    }

    public function testModifyDeletionProtection()
    {
        $this->mockAwsRdsClient()
            ->shouldReceive('modifyDBInstance')
            ->once();

        $this->rdsClient()->modifyDeletionProtection(Str::random(), $this->faker->boolean);
    }
}
