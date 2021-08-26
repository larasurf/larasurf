<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\AwsClient;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Exceptions\AwsClients\InvalidArgumentException;
use Symfony\Component\Console\Output\ConsoleOutput;

class AcmClient extends Client
{
    const VALIDATION_METHOD_DNS = 'DNS';

    const VALIDATION_METHODS = [
        self::VALIDATION_METHOD_DNS,
    ];

    public function requestCertificate(string $domain, string $validation_method = self::VALIDATION_METHOD_DNS, ConsoleOutput $output = null, string $wait_message = ''): DnsRecord
    {
        $this->validateValidationMethod($validation_method);

        $result = $this->client->requestCertificate([
            'DomainName' => $domain,
            'ValidationMethod' => $validation_method,
            'Tags' => $this->resourceTags('acm-certificate'),
        ]);

        $client = $this->client;
        $arn = $result['CertificateArn'];

        $result = null;

        $this->waitForFinish(180, 10, function (&$success) use ($client, $arn, &$result) {
            $result = $client->describeCertificate([
                'CertificateArn' => $arn,
            ]);

            if (isset($result['Certificate']['DomainValidationOptions'])) {
                $record_name = $result['Certificate']['DomainValidationOptions'][0]['ResourceRecord']['Name'] ?? '';
                $record_value = $result['Certificate']['DomainValidationOptions'][0]['ResourceRecord']['Value'] ?? '';
                $finished = !empty($record_name) && !empty($record_value);

                if ($finished) {
                    $success = true;

                    return true;
                }
            }

            return false;
        }, $output, $wait_message);

        return (new DnsRecord())
            ->setName($result['Certificate']['DomainValidationOptions'][0]['ResourceRecord']['Name'])
            ->setValue($result['Certificate']['DomainValidationOptions'][0]['ResourceRecord']['Value'])
            ->setType(DnsRecord::TYPE_CNAME);
    }

    public function deleteCertificate(string $arn)
    {
        $this->client->deleteCertificate([
            'CertificateArn' => $arn,
        ]);
    }

    public function certificateStatus(string $arn): string
    {
        $result = $this->client->describeCertificate([
            'CertificateArn' => $arn,
        ]);

        return $result['Certificate']['Status'] ?? 'UNKNOWN';
    }

    protected function makeClient(array $args): AwsClient
    {
        return new \Aws\Acm\AcmClient($args);
    }

    protected function validateValidationMethod(string $validation_method)
    {
        if (!in_array($validation_method, self::VALIDATION_METHODS)) {
            throw new InvalidArgumentException('validation_method');
        }
    }
}
