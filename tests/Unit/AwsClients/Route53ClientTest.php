<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Tests\TestCase;

class Route53ClientTest extends TestCase
{
    public function testHostedZoneIdFromDomainSuccess()
    {
        $domain = $this->faker->word . '.com';
        $id = Str::random();

        $this->mockAwsRoute53Client()
            ->shouldReceive('listHostedZones')
            ->andReturn([
                'HostedZones' => [
                    [
                        'Name' => $domain . '.',
                        'Id' => "/hostedzone/$id",
                    ],
                ],
            ]);

        $this->assertEquals($id, $this->route53Client()->hostedZoneIdFromRootDomain($domain));
    }

    public function testHostedZoneIdFromDomainDoesntExist()
    {
        $domain = $this->faker->domainName;

        $this->mockAwsRoute53Client()
            ->shouldReceive('listHostedZones')
            ->andReturn([
                'HostedZones' => [
                    [
                        'Name' => Str::random(),
                        'Id' => Str::random(),
                    ],
                ],
            ]);

        $this->assertFalse($this->route53Client()->hostedZoneIdFromRootDomain($domain));
    }

    public function testUpsertDnsRecords()
    {
        $id = Str::random();

        $this->mockAwsRoute53Client()
            ->shouldReceive('changeResourceRecordSets')
            ->andReturn([
                'ChangeInfo' => [
                    'Id' => $id,
                ],
            ]);

        $records = [
            (new DnsRecord())
                ->setName(Str::random())
                ->setValue(Str::random())
                ->setType(Arr::random(DnsRecord::TYPES)),
        ];

        $this->assertEquals($id, $this->route53Client()->upsertDnsRecords(Str::random(), $records));
    }

    public function testWaitForChange()
    {
        $this->mockAwsRoute53Client()
            ->shouldReceive('getChange')
            ->andReturn([
                'ChangeInfo' => [
                    'Status' => 'INSYNC',
                ],
            ]);

        $this->route53Client()->waitForChange(Str::random());
    }

    public function testCreateHostedZone()
    {
        $id = Str::random();

        $this->mockAwsRoute53Client()
            ->shouldReceive('createHostedZone')
            ->andReturn([
                'HostedZone' => [
                    'Id' => $id,
                ],
            ]);

        $this->assertEquals($id, $this->route53Client()->createHostedZone($this->faker->domainName));
    }

    public function testHostedZoneNameServers()
    {
        $value = Str::random();

        $this->mockAwsRoute53Client()
            ->shouldReceive('listResourceRecordSets')
            ->andReturn([
                'ResourceRecordSets' => [
                    [
                        'ResourceRecords' => [
                            [
                                'Value' => $value,
                            ],
                        ],
                        'Type' => DnsRecord::TYPE_NS,
                    ],
                ],
            ]);

        $results = $this->route53Client()->hostedZoneNameServers(Str::random());

        $this->assertNotEmpty($results);
        $this->assertEquals($value, $results[0]);
    }
}
