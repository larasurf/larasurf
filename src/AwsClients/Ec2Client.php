<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\AwsClient;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\PrefixListEntry;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class Ec2Client extends Client
{
    public function allowIpPrefixList(string $id, string $ip)
    {
        $result = $this->client->describeManagedPrefixLists([
            'PrefixListIds' => [
                $id,
            ],
        ]);

        $cidr_description = $this->cidrWithDescriptionFromIpArgument($ip);

        $this->client->modifyManagedPrefixList([
            'AddEntries' => [
                [
                    'Cidr' => $cidr_description['cidr'],
                    'Description' => $cidr_description['description'],
                ],
            ],
            'CurrentVersion' => $result['PrefixLists'][0]['Version'],
            'PrefixListId' => $id,
        ]);
    }

    public function revokeIpPrefixList(string $id, string $ip)
    {
        $result = $this->client->describeManagedPrefixLists([
            'PrefixListIds' => [
                $id,
            ],
        ]);

        $cidr_description = $this->cidrWithDescriptionFromIpArgument($ip);

        $this->client->modifyManagedPrefixList([
            'RemoveEntries' => [
                [
                    'Cidr' => $cidr_description['cidr'],
                ],
            ],
            'CurrentVersion' => $result['PrefixLists'][0]['Version'],
            'PrefixListId' => $id,
        ]);
    }

    public function listIpsPrefixList(string $id)
    {
        $results = $this->client->getManagedPrefixListEntries([
            'PrefixListId' => $id,
        ]);

        return array_map(function ($entry) {
            return new PrefixListEntry($entry);
        }, $results['Entries']);
    }

    public function waitForPrefixListUpdate(string $id, ConsoleOutput $output = null, string $wait_message = '')
    {
        $client = $this->client;

        $this->waitForFinish(10, 3, function (&$success) use ($client, $id) {
            $result = $client->describeManagedPrefixLists([
                'PrefixListIds' => [
                    $id,
                ]
            ]);

            if (isset($result['PrefixLists'][0]['State'])) {
                $status = $result['PrefixLists'][0]['State'];

                $finished = !Str::endsWith($status, 'in-progress');

                if ($finished) {
                    $success = true;

                    return true;
                }
            }

            return false;
        }, $output, $wait_message);
    }

    protected function makeClient(array $args): AwsClient
    {
        return new \Aws\Ec2\Ec2Client($args);
    }

    protected function cidrWithDescriptionFromIpArgument($ip): array
    {
        if ($ip === 'me') {
            $my_ip = trim(file_get_contents('https://checkip.amazonaws.com'));
            $cidr = "$my_ip/32";
            $description = 'Private Access';
        } else if ($ip === 'public') {
            $cidr = '0.0.0.0/0';
            $description = 'Public Access';
        } else {
            $cidr = "$ip/32";
            $description = 'Private Access';
        }

        return [
            'cidr' => $cidr,
            'description' => $description,
        ];
    }
}
