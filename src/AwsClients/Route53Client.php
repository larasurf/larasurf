<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException;

class Route53Client extends Client
{
    public function hostedZoneIdFromDomain(string $domain): string|false
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

        return false;
    }

    public function upsertDnsRecords(string $hosted_zone_id, array $records): string
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

        return $result['ChangeInfo']['Id'];
    }

    public function waitForChange(string $change_id, OutputStyle $output = null, string $wait_message = '')
    {
        $client = $this->client;

        $this->waitForFinish(60, 30, function (&$success) use ($client, $change_id) {
            $result = $client->getChange([
                'Id' => $change_id,
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
        }, $output, $wait_message);
    }

    public function createHostedZone(string $domain): string
    {
        $result = $this->client->createHostedZone([
            'CallerReference' => Str::random(32),
            'Name' => $domain,
        ]);

        return $result['HostedZone']['Id'];
    }

    public function hostedZoneNameServers(string $hosted_zone_id): array
    {
        $result = $this->client->listResourceRecordSets([
            'HostedZoneId' => $hosted_zone_id,
        ]);

        $results = [];

        foreach ($result['ResourceRecordSets'] as $record_set) {
            if ($record_set['Type'] === DnsRecord::TYPE_NS) {
                foreach ($record_set['ResourceRecords'] as $record) {
                    $results[] = $record['Value'];
                }
            }
        }

        return $results;
    }

    protected function makeClient(array $args): \Aws\Route53\Route53Client
    {
        return new \Aws\Route53\Route53Client($args);
    }
}
