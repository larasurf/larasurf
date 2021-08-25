<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\AwsClient;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\Output\Input\DnsRecord;
use LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException;

class Route53Client extends Client
{
    public function hostedZoneIdFromDomain(string $domain): string|null
    {
        // todo: support more than 100 hosted zones
        $hosted_zones = $this->client->listHostedZones();

        $suffix = Str::afterLast($domain, '.');
        $domain_length = strlen($domain) - strlen($suffix) - 1;
        $domain = substr($domain, 0, $domain_length);

        if (Str::contains($domain, '.')) {
            $domain = Str::afterLast($domain, '.');
        }

        $domain .= '.' . $suffix;

        foreach ($hosted_zones['HostedZones'] as $hosted_zone) {
            if ($hosted_zone['Name'] === $domain . '.') {
                return str_replace('/hostedzone/', '', $hosted_zone['Id']);
            }
        }

        return null;
    }

    public function upsertDnsRecords(string $hosted_zone_id, array $records)
    {
        $changes = [];

        foreach ($records as $record) {
            if (!$record instanceof DnsRecord) {
                throw new ExpectedArrayOfTypeException(DnsRecord::class);
            }

            $changes[] = [
                'Action' => 'UPSERT',
                'ResourceRecordSet' => $record->toArray(),
            ];
        }

        $result = $this->client->changeResourceRecordSets([
            'ChangeBatch' => [
                'Changes' => $changes,
                'Comment' => 'Created by LaraSurf',
            ],
            'HostedZoneId' => $hosted_zone_id,
        ]);

        $id = $result['ChangeInfo']['Id'];

        $client = $this->client;

        $this->waitForFinish(180, 10, function (&$success) use ($client, $id) {
            $result = $client->getChange([
                'Id' => $id,
            ]);

            if (isset($result['ChangeInfo']['Status'])) {
                $status = $result['ChangeInfo']['Status'];
                $finished = $status === 'INSYNC';

                if ($finished) {
                    $success = true;

                    return true;
                }
            }

            return false;
        });
    }

    protected function makeClient(array $args): AwsClient
    {
        return new \Aws\Route53\Route53Client($args);
    }
}
