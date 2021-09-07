<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException;

class EcsClient extends Client
{
    public function runTask(array $security_groups, array $subnets, array $command, string $task_definition): string|false
    {
        $result = $this->client->runTask([
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

        return $result['tasks'][0]['taskArn'] ?? false;
    }

    public function waitForTaskFinish(string $arn, OutputStyle $output = null, $wait_message = '')
    {
        $client = $this->client;

        $this->waitForFinish(60, 60, function (&$success) use ($client, $arn) {
            $result = $client->describeTasks([
                'tasks' => [$arn],
            ]);

            if (isset($result['tasks'][0]['lastStatus'])) {
                $status = $result['tasks'][0]['lastStatus'];
                $finished = $status === 'STOPPED';

                if ($finished) {
                    $success = true;

                    return true;
                }
            }

            return false;
        }, $output, $wait_message);
    }
    
    protected function makeClient(array $args): \Aws\Ecs\EcsClient
    {
        return new \Aws\Ecs\EcsClient($args);
    }
}
