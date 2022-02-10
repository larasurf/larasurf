<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\PrefixListEntry;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudIngressTest extends TestCase
{
    public function testAllow()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn(Str::random());

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('allowIpPrefixList')->once()->andReturn();
        $ec2->shouldReceive('waitForPrefixListUpdate')->once()->andReturn();

        $this->artisan('larasurf:cloud-ingress allow --environment production --type application --source public')
            ->expectsOutput('Prefix List updated successfully')
            ->assertExitCode(0);
    }

    public function testAllowStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn(false);

        $this->artisan('larasurf:cloud-ingress allow --environment production --type application --source public')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    public function testDisallow()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn(Str::random());

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('revokeIpPrefixList')->once()->andReturn();
        $ec2->shouldReceive('waitForPrefixListUpdate')->once()->andReturn();

        $this->artisan('larasurf:cloud-ingress revoke --environment production --type application --source public')
            ->expectsOutput('Prefix List updated successfully')
            ->assertExitCode(0);
    }

    public function testDisallowStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn(false);

        $this->artisan('larasurf:cloud-ingress revoke --environment production --type application --source public')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    public function testList()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn(Str::random());

        $ip1 = $this->faker->ipv4;
        $ip2 = $this->faker->ipv4;

        $ec2 = $this->mockLaraSurfEc2Client();
        $ec2->shouldReceive('listIpsPrefixList')->once()->andReturn([
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

    public function testListStackDoesntExist()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn(false);

        $this->artisan('larasurf:cloud-ingress list --environment production --type application')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }
}
