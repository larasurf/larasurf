<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class Ec2ClientTest extends TestCase
{
    public function testAllowIpPrefixListMe()
    {
        Http::fake([
            'https://checkip.amazonaws.com' => Http::response($this->faker->ipv4),
        ]);

        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [
                    [
                        'Version' => 1,
                    ],
                ],
            ])
            ->shouldReceive('modifyManagedPrefixList');

        $this->ec2Client()->allowIpPrefixList(Str::random(), 'me');
    }

    public function testAllowIpPrefixListPublic()
    {
        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [
                    [
                        'Version' => 1,
                    ],
                ],
            ])
            ->shouldReceive('modifyManagedPrefixList');

        $this->ec2Client()->allowIpPrefixList(Str::random(), 'public');
    }

    public function testAllowIpPrefixListArbitrary()
    {
        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [
                    [
                        'Version' => 1,
                    ],
                ],
            ])
            ->shouldReceive('modifyManagedPrefixList');

        $this->ec2Client()->allowIpPrefixList(Str::random(), $this->faker->ipv4);
    }
    
    
    public function testRevokeIpPrefixListMe()
    {
        Http::fake([
            'https://checkip.amazonaws.com' => Http::response($this->faker->ipv4),
        ]);

        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [
                    [
                        'Version' => 1,
                    ],
                ],
            ])
            ->shouldReceive('modifyManagedPrefixList');

        $this->ec2Client()->revokeIpPrefixList(Str::random(), 'me');
    }

    public function testRevokeIpPrefixListPublic()
    {
        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [
                    [
                        'Version' => 1,
                    ],
                ],
            ])
            ->shouldReceive('modifyManagedPrefixList');

        $this->ec2Client()->revokeIpPrefixList(Str::random(), 'public');
    }

    public function testRevokeIpPrefixListArbitrary()
    {
        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [
                    [
                        'Version' => 1,
                    ],
                ],
            ])
            ->shouldReceive('modifyManagedPrefixList');

        $this->ec2Client()->revokeIpPrefixList(Str::random(), $this->faker->ipv4);
    }

    public function testListIpsPrefixList()
    {
        $cidr1 = $this->faker->ipv4 . '/32';
        $description1 = $this->faker->words(5, true);

        $cidr2 = '0.0.0.0/0';
        $description2 = $this->faker->words(5, true);

        $this->mockAwsEc2Client()
            ->shouldReceive('getManagedPrefixListEntries')
            ->andReturn([
                'Entries' => [
                    [
                        'Cidr' => $cidr1,
                        'Description' => $description1,
                    ],
                    [
                        'Cidr' => $cidr2,
                        'Description' => $description2,
                    ],
                ],
            ]);

        $results = $this->ec2Client()->listIpsPrefixList(Str::random());

        $this->assertNotEmpty($results);

        $this->assertEquals($cidr1, $results[0]->getCidr());
        $this->assertEquals($description1, $results[0]->getDescription());

        $this->assertEquals($cidr2, $results[1]->getCidr());
        $this->assertEquals($description2, $results[1]->getDescription());
    }

    public function testWaitForPrefixListUpdateSuccess()
    {
        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [
                    [
                        'State' => 'create-complete',
                    ],
                ],
            ]);

        $result = $this->ec2Client()->waitForPrefixListUpdate(Str::random());

        $this->assertTrue($result);
    }

    public function testWaitForPrefixListUpdateDoesntExist()
    {
        $this->mockAwsEc2Client()
            ->shouldReceive('describeManagedPrefixLists')
            ->andReturn([
                'PrefixLists' => [],
            ]);

        $result = $this->ec2Client()->waitForPrefixListUpdate(Str::random());

        $this->assertFalse($result);
    }
}
