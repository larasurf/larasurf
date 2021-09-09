<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException;

class EcsClient extends Client
{
    public function runTask(string $cluster_arn, array $security_groups, array $subnets, array $command, string $task_definition, string $container_name = 'artisan'): string|false
    {
        $result = $this->client->runTask([
            'cluster' => $cluster_arn,
            'launchType' => 'FARGATE',
            'networkConfiguration' => [
                'awsvpcConfiguration' => [
                    'assignPublicIp' => 'ENABLED',
                    'securityGroups' => $security_groups,
                    'subnets' => $subnets,
                ],
            ],
            'overrides' => [
                'containerOverrides' => [
                    [
                        'command' => $command,
                        'name' => $container_name,
                    ],
                ],
            ],
            'taskDefinition' => $task_definition,
        ]);

        return $result['tasks'][0]['taskArn'] ?? false;
    }

    public function waitForTaskFinish(string $cluster_arn, string $task_arn, OutputStyle $output = null, $wait_message = '')
    {
        $client = $this->client;

        $this->waitForFinish(60, 60, function (&$success) use ($client, $cluster_arn, $task_arn) {
            $result = $client->describeTasks([
                'cluster' => $cluster_arn,
                'tasks' => [$task_arn],
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
