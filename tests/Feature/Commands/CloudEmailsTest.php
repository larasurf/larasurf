<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Tests\TestCase;

class CloudEmailsTest extends TestCase
{
    public function testVerifyDomain()
    {
        $domain = $this->faker->domainName;

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->twice()->andReturn($domain);

        $ses = $this->mockLaraSurfSesClient();
        $ses->shouldReceive('verifyDomain')
            ->once()
            ->andReturn(new DnsRecord([
                'Name' => $this->faker->word,
                'ResourcesRecords' => [
                    [
                        'Value' => Str::random(),
                    ],
                ],
                'TTL' => random_int(100, 1000),
                'Type' => DnsRecord::TYPE_TXT,
            ]));
        $ses->shouldReceive('waitForDomainVerification')->once()->andReturn();
        $ses->shouldReceive('verifyDomainDkim')->once()->andReturn([new DnsRecord([
            'Name' => $this->faker->word,
            'ResourcesRecords' => [
                [
                    'Value' => Str::random(),
                ],
            ],
            'TTL' => random_int(100, 1000),
            'Type' => DnsRecord::TYPE_CNAME,
        ])]);
        $ses->shouldReceive('waitForDomainDkimVerification')->once()->andReturn();

        $route53 = $this->mockLaraSurfRoute53Client();
        $route53->shouldReceive('upsertDnsRecords')->once()->andReturn(Str::random());
        $route53->shouldReceive('waitForChange')->twice()->andReturn();
        $route53->shouldReceive('upsertDnsRecords')->once()->andReturn(Str::random());

        $this->artisan('larasurf:cloud-emails verify-domain --environment production')
            ->expectsOutput("Verifying email domain '$domain'...")
            ->expectsOutput('Email domain verified successfully')
            ->expectsOutput("Verifying email domain '$domain' for DKIM...")
            ->expectsOutput('Email domain verified for DKIM successfully')
            ->assertExitCode(0);
    }

    public function testVerifyDomainStackDoesntExist()
    {
        $this->mockLaraSurfCloudFormationClient()
            ->shouldReceive('stackStatus')->once()
            ->andReturn(false);

        $this->artisan('larasurf:cloud-emails verify-domain --environment production')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    public function testCheckVerification()
    {
        $domain = $this->faker->domainName;

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn($domain);

        $ses = $this->mockLaraSurfSesClient();
        $ses->shouldReceive('checkDomainVerification')->once()->andReturn(true);
        $ses->shouldReceive('checkDomainDkimVerification')->once()->andReturn(true);

        $this->artisan('larasurf:cloud-emails check-verification --environment production')
            ->expectsOutput("Domain '$domain' is verified for email sending")
            ->expectsOutput("Domain '$domain' is verified for DKIM")
            ->assertExitCode(0);
    }

    public function testCheckVerificationStackDoesntExist()
    {
        $this->mockLaraSurfCloudFormationClient()
            ->shouldReceive('stackStatus')
            ->once()
            ->andReturn(false);

        $this->artisan('larasurf:cloud-emails check-verification --environment production')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    public function testCheckVerificationNotVerified()
    {
        $domain = $this->faker->domainName;

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn($domain);

        $ses = $this->mockLaraSurfSesClient();
        $ses->shouldReceive('checkDomainVerification')->once()->andReturn(false);
        $ses->shouldReceive('checkDomainDkimVerification')->once()->andReturn(true);

        $this->artisan('larasurf:cloud-emails check-verification --environment production')
            ->expectsOutput("Domain '$domain' is not verified for email sending")
            ->expectsOutput("Domain '$domain' is verified for DKIM")
            ->assertExitCode(1);
    }

    public function testCheckVerificationDkimNotVerified()
    {
        $domain = $this->faker->domainName;

        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn($domain);

        $ses = $this->mockLaraSurfSesClient();
        $ses->shouldReceive('checkDomainVerification')->once()->andReturn(true);
        $ses->shouldReceive('checkDomainDkimVerification')->once()->andReturn(false);

        $this->artisan('larasurf:cloud-emails check-verification --environment production')
            ->expectsOutput("Domain '$domain' is verified for email sending")
            ->expectsOutput("Domain '$domain' is not verified for DKIM")
            ->assertExitCode(1);
    }

    public function testEnableSending()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');
        $cloudformation->shouldReceive('stackOutput')->once()->andReturn($this->faker->domainName);

        $ses = $this->mockLaraSurfSesClient();
        $ses->shouldReceive('checkEmailSending')->once()->andReturn(false);
        $ses->shouldReceive('enableEmailSending')->once()->andReturn();

        $this->artisan('larasurf:cloud-emails enable-sending')
            ->expectsQuestion('Use Case Description', $this->faker->sentence)
            ->expectsQuestion('Website URL', $this->faker->domainName)
            ->expectsOutput('Requested live email sending successfully')
            ->expectsOutput('Response from AWS may take up to 24 hours')
            ->assertExitCode(0);
    }

    public function testEnableSendingStackDoesntExist()
    {
        $this->mockLaraSurfCloudFormationClient()
            ->shouldReceive('stackStatus')
            ->once()
            ->andReturn(false);

        $this->artisan('larasurf:cloud-emails enable-sending')
            ->expectsOutput("Stack does not exist for the 'production' environment")
            ->assertExitCode(1);
    }

    public function testEnableSendingAlreadyEnabled()
    {
        $cloudformation = $this->mockLaraSurfCloudFormationClient();
        $cloudformation->shouldReceive('stackStatus')->once()->andReturn('CREATE_COMPLETE');

        $ses = $this->mockLaraSurfSesClient();
        $ses->shouldReceive('checkEmailSending')->once()->andReturn(true);

        $this->artisan('larasurf:cloud-emails enable-sending')
            ->expectsOutput('Live email sending is already enabled')
            ->assertExitCode(0);
    }

    public function testCheckSending()
    {
        $this->mockLaraSurfSesClient()->shouldReceive('checkEmailSending')->once()->andReturn(true);

        $this->artisan('larasurf:cloud-emails check-sending')
            ->expectsOutput('Live email sending is enabled')
            ->assertExitCode(0);
    }

    public function testCheckSendingNotEnabled()
    {
        $this->mockLaraSurfSesClient()->shouldReceive('checkEmailSending')->once()->andReturn(false);

        $this->artisan('larasurf:cloud-emails check-sending')
            ->expectsOutput('Live email sending is not enabled')
            ->assertExitCode(1);
    }
}
