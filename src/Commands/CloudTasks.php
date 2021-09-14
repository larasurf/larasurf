<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudTasks extends Command
{
    use HasSubCommands;
    use HasEnvironmentOption;
    use InteractsWithAws;

    const COMMAND_RUN_FOR_EXEC = 'run-for-exec';
    const COMMAND_STOP = 'stop';

    protected $signature = 'larasurf:cloud-tasks
                            {--environment= : The environment: \'stage\' or \'production\'}
                            {--task=null : The task ID or ARN}
                            {subcommand : The subcommand to run: \'run\' or \'stop\'}';

    protected $description = 'Manage container tasks in cloud environments';

    protected array $commands = [
        self::COMMAND_RUN_FOR_EXEC => 'handleRunForExec',
        self::COMMAND_STOP => 'handleStop',
    ];

    public function handleRunForExec()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $aws_region = static::larasurfConfig()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error("AWS region is not set for the '$env' environment; create image repositories first");

            return 1;
        }

        $outputs = $this->awsCloudFormation($env, $aws_region)->stackOutput([
            'ContainerClusterArn',
            'DBSecurityGroupId',
            'CacheSecurityGroupId',
            'ContainersSecurityGroupId',
            'Subnet1Id',
            'ArtisanTaskDefinitionArn',
        ]);

        $security_groups = [
            $outputs['DBSecurityGroupId'],
            $outputs['CacheSecurityGroupId'],
            $outputs['ContainersSecurityGroupId'],
        ];

        $subnets = [$outputs['Subnet1Id']];

        $command = ['/bin/bash', '-c', 'trap : TERM INT; sleep infinity & wait'];

        $ecs = $this->awsEcs($env, $aws_region);

        $this->info('Starting ECS task...');

        $task_arn = $ecs->runTask($outputs['ContainerClusterArn'], $security_groups, $subnets, $command, $outputs['ArtisanTaskDefinitionArn'], 'artisan', true);

        if (!$task_arn) {
            $this->error('Failed to start ECS task');

            return false;
        }

        $this->getOutput()->writeln($task_arn);

        return 0;
    }

    public function handleStop()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $task = $this->taskOption();

        if (!$task) {
            return 1;
        }

        $aws_region = static::larasurfConfig()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error("AWS region is not set for the '$env' environment; create image repositories first");

            return 1;
        }

        $this->info('Stopping ECS task...');

        $cluster = $this->awsCloudFormation($env, $aws_region)->stackOutput('ContainerClusterArn');


        $ecs = $this->awsEcs($env, $aws_region);
        $ecs->stopTask($cluster, $task);

        $this->info('Signaled task to stop successfully');

        return 0;
    }

    protected function taskOption(): string|false
    {
        $task = $this->option('task');

        if (!$task || $task === 'null') {
            $this->error('You must specify a task');

            return false;
        }

        return $task;
    }
}
