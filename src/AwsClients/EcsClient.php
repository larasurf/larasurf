<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException;

class EcsClient extends Client
{
    public function runTask(array $security_groups, array $subnets, array $command, string $task_definition)
    {
        $this->client->runTask([
            'launchType' => 'FARGATE',
            'networkConfiguration' => [
                'awsvpcConfiguration' => [
                    'assignPublicIp' => 'DISABLED',
                    'securityGroups' => $security_groups,
                    'subnets' => $subnets,
                ],
            ],
            'overrides' => [
                'containerOverrides' => [
                    [
                        'command' => $command,
                    ],
                ],
            ],
            'taskDefinition' => $task_definition,
        ]);
    }
    
    protected function makeClient(array $args): \Aws\Ecs\EcsClient
    {
        return new \Aws\Ecs\EcsClient($args);
    }
}
