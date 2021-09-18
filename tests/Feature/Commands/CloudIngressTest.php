<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\PrefixListEntry;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudIngressTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllow()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->andReturn(Str::random());

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('allowIpPrefixList')->andReturn();
        $ec2->shouldReceive('waitForPrefixListUpdate')->andReturn();

        $this->artisan('larasurf:cloud-ingress allow --environment production --type application --source public')
            ->expectsOutput('Prefix List updated successfully')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllowStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);

        $this->artisan('larasurf:cloud-ingress allow --environment production --type application --source public')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDisallow()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->andReturn(Str::random());

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('revokeIpPrefixList')->andReturn();
        $ec2->shouldReceive('waitForPrefixListUpdate')->andReturn();

        $this->artisan('larasurf:cloud-ingress revoke --environment production --type application --source public')
            ->expectsOutput('Prefix List updated successfully')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDisallowStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);

        $this->artisan('larasurf:cloud-ingress revoke --environment production --type application --source public')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testList()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->andReturn(Str::random());

        $ip1 = $this->faker->ipv4;
        $ip2 = $this->faker->ipv4;

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('listIpsPrefixList')->andReturn([
            new PrefixListEntry([
                'Cidr' => $ip1 . '/32',
                'Description' => 'Private Access',
            ]),
            new PrefixListEntry([
                'Cidr' => $ip2 . '/32',
                'Description' => 'Private Access',
            ]),
        ]);

        $this->artisan('larasurf:cloud-ingress list --environment production --type application')
            ->expectsOutput("$ip1/32: Private Access")
            ->expectsOutput("$ip2/32: Private Access")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testListStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->andReturn(false);

        $this->artisan('larasurf:cloud-ingress list --environment production --type application')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }
}
