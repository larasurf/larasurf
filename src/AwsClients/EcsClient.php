<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DnsRecord;
use LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException;

class EcsClient extends Client
{
    public function listRunningTasks(string $cluster_arn): array|false
    {
        // todo: support more than 100 tasks

        $result = $this->client->listTasks([
            'cluster' => $cluster_arn,
            'desiredStatus' => 'RUNNING',
            'launchType' => 'FARGATE',
        ]);

        return $result['taskArns'] ?? false;
    }

    public function executeCommand(string $cluster_arn, string $task_arn, string $container, string $command, bool $interactive)
    {
        $this->client->executeCommand([
            'cluster' => $cluster_arn,
            'command' => $command,
            'container' => $container,
            'interactive' => $interactive,
            'task' => $task_arn,
        ]);
    }
    
    public function runTask(string $cluster_arn, array $security_groups, array $subnets, array $command, string $task_definition, string $container_name = 'artisan', bool $enabled_execute_command = false): string|false
    {
        $result = $this->client->runTask([
            'cluster' => $cluster_arn,
            'enableExecuteCommand' => $enabled_execute_command,
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

    public function stopTask(string $cluster_arn, string $task)
    {
        $this->client->stopTask([
            'cluster' => $cluster_arn,
            'task' => $task,
        ]);
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
