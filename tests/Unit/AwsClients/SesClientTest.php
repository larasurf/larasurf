<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Tests\TestCase;

class SesClientTest extends TestCase
{
    public function testVerifyDomain()
    {
        $token = Str::random();
        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('verifyDomainIdentity')
            ->andReturn([
                'VerificationToken' => $token,
            ]);

        $dns_record = $this->sesClient()->verifyDomain($domain);

        $this->assertEquals("_amazonses.$domain", $dns_record->getName());
        $this->assertEquals('"'. $token . '"', $dns_record->getValue());
        $this->assertEquals(DnsRecord::TYPE_TXT, $dns_record->getType());
    }

    public function testVerifyDomainDkim()
    {
        $tokens = [
            Str::random(),
            Str::random(),
        ];

        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('verifyDomainDkim')
            ->andReturn([
                'DkimTokens' => $tokens,
            ]);

        $dns_records = $this->sesClient()->verifyDomainDkim($domain);

        foreach ($dns_records as $index => $dns_record) {
            $this->assertEquals("{$tokens[$index]}._domainkey.$domain", $dns_record->getName());
            $this->assertEquals("{$tokens[$index]}.dkim.amazonses.com", $dns_record->getValue());
            $this->assertEquals(DnsRecord::TYPE_CNAME, $dns_record->getType());
        }
    }

    public function testWaitForDomainVerification()
    {
        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('getIdentityVerificationAttributes')
            ->andReturn([
                'VerificationAttributes' => [
                     $domain => [
                        'VerificationStatus' => 'Success',
                    ],
                ],
            ]);

        $this->sesClient()->waitForDomainVerification($domain);
    }

    public function testCheckDomainVerificationSuccess()
    {
        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('getIdentityVerificationAttributes')
            ->andReturn([
                'VerificationAttributes' => [
                    $domain => [
                        'VerificationStatus' => 'Success',
                    ],
                ],
            ]);

        $this->assertTrue($this->sesClient()->checkDomainVerification($domain));
    }

    public function testCheckDomainVerificationFailure()
    {
        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('getIdentityVerificationAttributes')
            ->andReturn([
                'VerificationAttributes' => [
                    $domain => [
                        'VerificationStatus' => $this->faker->word,
                    ],
                ],
            ]);

        $this->assertFalse($this->sesClient()->checkDomainVerification($domain));
    }

    public function testWaitForDomainDkimVerification()
    {
        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('getIdentityDkimAttributes')
            ->andReturn([
                'DkimAttributes' => [
                    $domain => [
                        'DkimVerificationStatus' => 'Success',
                    ],
                ],
            ]);

        $this->sesClient()->waitForDomainDkimVerification($domain);
    }

    public function testCheckDomainDkimVerificationSuccess()
    {
        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('getIdentityDkimAttributes')
            ->andReturn([
                'DkimAttributes' => [
                    $domain => [
                        'DkimVerificationStatus' => 'Success',
                    ],
                ],
            ]);

        $this->assertTrue($this->sesClient()->checkDomainDkimVerification($domain));
    }

    public function testDeleteDomain()
    {
        $domain = $this->faker->domainName;

        $this->mockAwsSesClient()
            ->shouldReceive('getIdentityDkimAttributes')
            ->andReturn([
                'DkimAttributes' => [
                    $domain => [
                        'DkimVerificationStatus' => $this->faker->word,
                    ],
                ],
            ]);

        $this->assertFalse($this->sesClient()->checkDomainDkimVerification($domain));

    }

    public function testEmailSending()
    {
        $this->mockAwsSesClient();

        $enabled = $this->faker->boolean;

        $this->mockAwsSesV2Client()
            ->shouldReceive('putAccountDetails')
            ->andReturn()->shouldReceive('getAccount')
            ->andReturn([
                'ProductionAccessEnabled' => $enabled,
            ]);

        $this->sesClient()->enableEmailSending($this->faker->url, $this->faker->words(5, true));
        $this->assertEquals($enabled, $this->sesClient()->checkEmailSending());
    }
}
