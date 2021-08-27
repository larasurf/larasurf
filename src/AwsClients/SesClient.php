<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\SesV2\SesV2Client;
use Illuminate\Console\OutputStyle;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;;

class SesClient extends Client
{
    protected static ?SesV2Client $v2_client = null;

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

    public function waitForDomainVerification(string $domain, OutputStyle $output = null, $wait_message = '')
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

    public function checkDomainVerification(string $domain): bool
    {
        $result = $this->client->getIdentityVerificationAttributes([
            'Identities' => [
                $domain,
            ],
        ]);

        return ($result['VerificationAttributes'][$domain]['VerificationStatus'] ?? false) === 'Success';
    }

    public function waitForDomainDkimVerification(string $domain, OutputStyle $output = null, $wait_message = '')
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

    public function checkDomainDkimVerification(string $domain): bool
    {
        $result = $this->client->getIdentityDkimAttributes([
            'Identities' => [
                $domain,
            ],
        ]);

        return ($result['DkimAttributes'][$domain]['DkimVerificationStatus'] ?? false) === 'Success';
    }

    public function deleteDomain(string $domain)
    {
        $this->client->deleteIdentity([
            'Identity' => $domain,
        ]);
    }

    public function enableEmailSending(string $website, string $description)
    {
        $this->makeV2Client()->putAccountDetails([
            'MailType' => 'TRANSACTIONAL',
            'ProductionAccessEnabled' => true,
            'UseCaseDescription' => $description,
            'WebsiteURL' => $website,
        ]);
    }

    public function checkEmailSending(): bool
    {
        $result = $this->makeV2Client()->getAccount();

        return (bool) $result['ProductionAccessEnabled'] ?? false;
    }

    protected function makeClient(array $args): \Aws\Ses\SesClient
    {
        return new \Aws\Ses\SesClient($args);
    }

    protected function makeV2Client(): SesV2Client
    {
        if (!static::$v2_client) {
            static::$v2_client = new SesV2Client($this->clientArguments());
        }

        return static::$v2_client;
    }
}
