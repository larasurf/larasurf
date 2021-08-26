<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\AwsClient;
use Aws\SesV2\SesV2Client;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use Symfony\Component\Console\Output\ConsoleOutput;

class SesClient extends Client
{
    protected static ?SesV2Client $v2_client;

    public function verifyDomain(string $domain): DnsRecord
    {
        $result = $this->client->verifyDomainIdentity([
            'Domain' => $domain,
        ]);

        return (new DnsRecord())
            ->setName("_amazonses.$domain")
            ->setValue($result['VerificationToken'])
            ->setType(DnsRecord::TYPE_TXT);
    }

    public function verifyDomainDkim($domain): array
    {
        $result = $this->client->verifyDomainDkim([
            'Domain' => $domain,
        ]);

        return array_map(function ($token) use ($domain) {
            return (new DnsRecord())
                ->setName("$token._domainkey.$domain")
                ->setValue("$token.dkim.amazonses.com")
                ->setType(DnsRecord::TYPE_CNAME);
        }, $result['DkimTokens']);
    }

    public function waitForDomainVerification(string $domain, ConsoleOutput $output = null, $wait_message = '')
    {
        $client = $this->client;

        $this->waitForFinish(180, 10, function (&$success) use ($client, $domain) {
            $result = $client->getIdentityVerificationAttributes([
                'Identities' => [
                    $domain,
                ],
            ]);

            if (isset($result['VerificationAttributes'][$domain])) {
                $status = $result['VerificationAttributes'][$domain]['VerificationStatus'];
                $finished = $status === 'Success';

                if ($finished) {
                    $success = true;

                    return true;
                }
            }

            return false;
        }, $output, $wait_message);
    }

    public function waitForDomainDkimVerification(string $domain, ConsoleOutput $output = null, $wait_message = '')
    {
        $client = $this->client;

        $this->waitForFinish(180, 10, function (&$success) use ($client, $domain) {
            $result = $client->getIdentityDkimAttributes([
                'Identities' => [
                    $domain,
                ],
            ]);

            if (isset($result['DkimAttributes'][$domain])) {
                $status = $result['DkimAttributes'][$domain]['DkimVerificationStatus'];
                $finished = $status === 'Success';

                if ($finished) {
                    $success = true;

                    return true;
                }
            }

            return false;
        }, $output, $wait_message);
    }

    public function deleteDomain(string $domain)
    {
        $this->client->deleteIdentity([
            'Identity' => $domain,
        ]);
    }

    public function enableEmailSending(string $domain, string $description)
    {
        $this->makeV2Client()->putAccountDetails([
            'MailType' => 'TRANSACTIONAL',
            'ProductionAccessEnabled' => true,
            'UseCaseDescription' => $description,
            'WebsiteURL' => "https://$domain",
        ]);
    }

    public function checkEmailSending()
    {
        $result = $this->makeV2Client()->getAccount();

        return $result['ProductionAccessEnabled'] ?? false;
    }

    protected function makeClient(array $args): AwsClient
    {
        return new \Aws\Ses\SesClient($args);
    }

    protected function makeV2Client()
    {
        if (!static::$v2_client) {
            static::$v2_client = new SesV2Client($this->clientArguments());
        }

        return static::$v2_client;
    }
}
