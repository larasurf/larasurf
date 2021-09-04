<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\Exception\AwsException;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\AccessKey;

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

    public function createAccessKey(string $user_name): AccessKey
    {
        $result = $this->client->createAccessKey([
            'UserName' => $user_name,
        ]);

        return new AccessKey($result['AccessKey']);
    }

    public function deleteAccessKey(string $user_name, string $access_key_id)
    {
        $this->client->deleteAccessKey([
            'AccessKeyId' => $access_key_id,
            'UserName' => $user_name,
        ]);
    }

    public function listAccessKeys(string $user_name)
    {
        $result = $this->client->listAccessKeys([
            'UserName' => $user_name,
        ]);

        return array_map(fn ($key) => $key['AccessKeyId'], $result['AccessKeyMetadata']);
    }

    public function attachUserPolicy(string $user_name, string $policy_arn)
    {
        $this->client->attachUserPolicy([
            'PolicyArn' => $policy_arn,
            'UserName' => $user_name,
        ]);
    }

    public function detachUserPolicy(string $user_name, string $policy_arn)
    {
        $this->client->detachUserPolicy([
            'PolicyArn' => $policy_arn,
            'UserName' => $user_name,
        ]);
    }

    protected function makeClient(array $args): \Aws\Iam\IamClient
    {
        return new \Aws\Iam\IamClient($args);
    }
}
