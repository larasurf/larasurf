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
            ->andReturn([
                'DBInstances' => [],
            ]);

        $this->assertFalse($this->rdsClient()->checkDeletionProtection(Str::random()));
    }

    public function testModifyDeletionProtection()
    {
        $this->mockAwsRdsClient()
            ->shouldReceive('modifyDBInstance');

        $this->rdsClient()->modifyDeletionProtection(Str::random(), $this->faker->boolean);
    }
}
