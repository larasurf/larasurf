<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\Exception\AwsException;

class RdsClient extends Client
{
    public function checkDeletionProtection(string $database_id): bool
    {
        try {
            $result = $this->client->describeDBInstances([
                'DBInstanceIdentifier' => $database_id,
            ]);
        } catch (AwsException $e) {
            return false;
        }

        return $result['DBInstances'][0]['DeletionProtection'] ?? false;
    }

    public function modifyDeletionProtection(string $database_id, bool $protection)
    {
        $this->client->modifyDBInstance([
            'ApplyImmediately' => true,
            'DBInstanceIdentifier' => $database_id,
            'DeletionProtection' => $protection,
        ]);
    }

    protected function makeClient(array $args): \Aws\Rds\RdsClient
    {
        return new \Aws\Rds\RdsClient($args);
    }
}
