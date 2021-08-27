<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Exceptions\AwsClients\InvalidArgumentException;
use LaraSurf\LaraSurf\Tests\TestCase;
use Mockery;

class AcmClientTest extends TestCase
{
    public function testRequestCertificate()
    {
        $arn = Str::random();
        $dns_name = $this->faker->word;
        $dns_value = $this->faker->word;

        Mockery::mock('overload:' . \Aws\Acm\AcmClient::class)
            ->shouldReceive('requestCertificate')
            ->andReturn([
                'CertificateArn' => $arn,
            ])
            ->shouldReceive('describeCertificate')
            ->andReturn([
                'Certificate' => [
                    'DomainValidationOptions' => [
                        [
                            'ResourceRecord' => [
                                'Name' => $dns_name,
                                'Value' => $dns_value,
                            ],
                        ],
                    ],
                ],
            ]);

        $dns_record = $this->acmClient()->requestCertificate($output_arn, $this->faker->domainName);

        $this->assertEquals($dns_name, $dns_record->getName());
        $this->assertEquals($dns_value, $dns_record->getValue());
        $this->assertEquals($arn, $output_arn);
    }

    public function testRequestCertificateInvalidValidationMethod()
    {
        $this->expectException(InvalidArgumentException::class);

        Mockery::mock('overload:' . \Aws\Acm\AcmClient::class);

        $this->acmClient()->requestCertificate($output_arn, $this->faker->domainName, $this->faker->word);
    }

    public function testWaitForPendingValidation()
    {
        $arn = Str::random();

        Mockery::mock('overload:' . \Aws\Acm\AcmClient::class)
            ->shouldReceive('describeCertificate')
            ->andReturn([
                'Certificate' => [
                    'Status' => 'ISSUED',
                ]
            ]);

        $this->acmClient()->waitForPendingValidation($arn);
    }

    public function testDeleteCertificate()
    {
        Mockery::mock('overload:' . \Aws\Acm\AcmClient::class)
            ->shouldReceive('deleteCertificate');

        $this->acmClient()->deleteCertificate(Str::random());
    }

    public function testCertificateStatusKnown()
    {
        $status = $this->faker->word;

        Mockery::mock('overload:' . \Aws\Acm\AcmClient::class)
            ->shouldReceive('describeCertificate')
            ->andReturn([
                'Certificate' => [
                    'Status' => $status,
                ]
            ]);

        $this->assertEquals($status, $this->acmClient()->certificateStatus(Str::random()));
    }

    public function testCertificateStatusUnknown()
    {
        Mockery::mock('overload:' . \Aws\Acm\AcmClient::class)
            ->shouldReceive('describeCertificate')
            ->andReturn([]);

        $this->assertEquals('UNKNOWN', $this->acmClient()->certificateStatus(Str::random()));
    }
}
