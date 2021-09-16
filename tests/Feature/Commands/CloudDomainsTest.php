<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;


use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudDomainsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHostedZoneExists()
    {
        $id = Str::random();

        $this->mockLaraSurfRoute53Client()
            ->shouldReceive('hostedZoneIdFromRootDomain')
            ->andReturn($id);

        $this->artisan('larasurf:cloud-domains hosted-zone-exists --domain ' . $this->faker->domainName)
            ->expectsOutput("Hosted zone exists with ID: $id")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHostedZoneExistsDoesntExist()
    {
        $this->mockLaraSurfRoute53Client()
            ->shouldReceive('hostedZoneIdFromRootDomain')
            ->andReturn(false);

        $domain = $this->faker->domainName;

        $this->artisan('larasurf:cloud-domains hosted-zone-exists --domain ' . $domain)
            ->expectsOutput("Hosted zone not found for domain '$domain'")
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateHostedZone()
    {
        $id = Str::random();

        $this->mockLaraSurfRoute53Client()
            ->shouldReceive('createHostedZone')
            ->andReturn($id);

        $this->artisan('larasurf:cloud-domains create-hosted-zone --domain ' . $this->faker->domainName)
            ->expectsOutput("Hosted zone created with ID: $id")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNameServers()
    {
        $nameservers = [
            $this->faker->domainName,
            $this->faker->domainName,
        ];

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')
            ->andReturn(Str::random());
        $route53->shouldReceive('hostedZoneNameServers')
            ->andReturn($nameservers);

        $this->artisan('larasurf:cloud-domains nameservers --domain ' . $this->faker->domainName)
            ->expectsOutput(implode(PHP_EOL, $nameservers))
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNameServersDoesntExist()
    {
        $domain = $this->faker->domainName;

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('hostedZoneIdFromRootDomain')
            ->andReturn(false);

        $this->artisan('larasurf:cloud-domains nameservers --domain ' . $domain)
            ->expectsOutput("Hosted zone not found for domain '$domain'")
            ->assertExitCode(1);
    }
}
