<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\Exception\AwsException;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\AccessKeys;

class IamClient extends Client
{
    public function userExists(string $user_name): bool
    {
        try {
            $this->client->getUser([
                'UserName' => $user_name,
            ]);
        } catch (AwsException $e) {
            return false;
        }

        return true;
    }

    public function createUser(string $user_name)
    {
        $this->client->createUser([
            'UserName' => $user_name,
        ]);
    }

    public function deleteUser(string $user_name)
    {
        $this->client->deleteUser([
            'UserName' => $user_name,
        ]);
    }

    public function createAccessKeys(string $user_name): AccessKeys
    {
        $result = $this->client->createAccessKey([
            'UserName' => $user_name,
        ]);

        return new AccessKeys($result);
    }

    public function attachUserPolicy(string $user_name, string $policy_arn)
    {
        $this->client->attachUserPolicy([
            'PolicyArn' => $policy_arn,
            'UserName' => $user_name,
        ]);
    }

    protected function makeClient(array $args): \Aws\Iam\IamClient
    {
        return new \Aws\Iam\IamClient($args);
    }
}
