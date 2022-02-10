<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;


use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudDomainsTest extends TestCase
{
    public function testHostedZoneExists()
    {
        $id = Str::random();

        $this->mockLaraSurfRoute53Client()
            ->shouldReceive('hostedZoneIdFromRootDomain')
            ->once()
            ->andReturn($id);

        $this->artisan('larasurf:cloud-domains hosted-zone-exists --domain ' . $this->faker->domainName)
            ->expectsOutput("Hosted zone exists with ID: $id")
            ->assertExitCode(0);
    }

    public function testHostedZoneExistsDoesntExist()
    {
        $this->mockLaraSurfRoute53Client()
            ->shouldReceive('hostedZoneIdFromRootDomain')
            ->once()
            ->andReturn(false);

        $domain = $this->faker->domainName;

        $this->artisan('larasurf:cloud-domains hosted-zone-exists --domain ' . $domain)
            ->expectsOutput("Hosted zone not found for domain '$domain'")
            ->assertExitCode(1);
    }

    public function testCreateHostedZone()
    {
        $id = Str::random();

        $this->mockLaraSurfRoute53Client()
            ->shouldReceive('createHostedZone')
            ->once()
            ->andReturn($id);

        $this->artisan('larasurf:cloud-domains create-hosted-zone --domain ' . $this->faker->domainName)
            ->expectsOutput("Hosted zone created with ID: $id")
            ->assertExitCode(0);
    }

    public function testNameServers()
    {
        $nameservers = [
            $this->faker->domainName,
            $this->faker->domainName,
        ];

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')
            ->once()
            ->andReturn(Str::random());
        $route53->shouldReceive('hostedZoneNameServers')
            ->once()
            ->andReturn($nameservers);

        $this->artisan('larasurf:cloud-domains nameservers --domain ' . $this->faker->domainName)
            ->expectsOutput(implode(PHP_EOL, $nameservers))
            ->assertExitCode(0);
    }

    public function testNameServersDoesntExist()
    {
        $domain = $this->faker->domainName;

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')
            ->once()
            ->andReturn(false);

        $this->artisan('larasurf:cloud-domains nameservers --domain ' . $domain)
            ->expectsOutput("Hosted zone not found for domain '$domain'")
            ->assertExitCode(1);
    }
}
