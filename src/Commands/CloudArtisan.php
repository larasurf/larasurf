<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudArtisan extends Command
{
    use InteractsWithAws;
    use HasEnvironmentOption;

    protected $signature = 'larasurf:cloud-artisan
                            {cmd : The full artisan command to run, in quotes}
                            {--environment= : The environment to run the artisan command on; \'stage\' or \'production\'}';

    protected $description = 'Run artisan commands on cloud environments';

    public function handle()
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

        $cloudformation = $this->awsCloudFormation($env, $aws_region);

        if (!$cloudformation->stackStatus()) {
            $this->error("Failed to determine stack status for the '$env' environment, has the stack been created?");

            return 1;
        }

        $artisan_command = $this->argument('cmd');

        if ($artisan_command === 'tinker') {
            return $this->tinker($cloudformation, $env, $aws_region) ? 0 : 1;
        }

        return $this->artisanCommand($cloudformation, $env, $aws_region, $artisan_command) ? 0 : 1;
    }

    protected function tinker(CloudFormationClient $cloudformation, string $env, string $aws_region): bool
    {
        $cluster_arn = $cloudformation->stackOutput('ContainerClusterArn');

        $ecs = $this->awsEcs($env, $aws_region);

        $tasks = $ecs->listRunningTasks($cluster_arn);

        if (empty($tasks[0])) {
            $this->error("No tasks running for the '$env' environment");

            return false;
        }

        $this->awsEcs($env, $aws_region)->executeCommand($cluster_arn, $tasks[0], 'app', 'php artisan tinker', true);

        return true;
    }

    protected function artisanCommand(CloudFormationClient $cloudformation, string $env, string $aws_region, string $command): bool
    {
        $command = ['php', 'artisan', ...explode(' ', $command)];

        $outputs = $cloudformation->stackOutput([
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

        $ecs = $this->awsEcs($env, $aws_region);
        $task_arn = $ecs->runTask($outputs['ContainerClusterArn'], $security_groups, $subnets, $command, $outputs['ArtisanTaskDefinitionArn']);

        if (!$task_arn) {
            $this->error('Failed to start ECS task to run artisan command');

            return false;
        }

        $this->info('Started ECS task to run artisan command successfully');

        $ecs->waitForTaskFinish(
            $outputs['ContainerClusterArn'],
            $task_arn,
            $this->getOutput(),
            'Task has not completed yet, checking again soon...'
        );

        $log_group = $cloudformation->stackOutput('ArtisanLogGroupName');

        if (!$log_group) {
            $this->error("Failed to find artisan log group for '$env' environment");

            return false;
        }

        $logs = $this->awsCloudWatchLogs($env, $aws_region)->listLogStream(
            $log_group,
            'artisan/artisan/' . Str::afterLast($task_arn, '/'),
        );

        if (!$logs) {
            $this->error("Failed to get events from artisan log group for '$env' environment");

            return false;
        }

        $this->info('Task output:');
        $this->getOutput()->writeln(implode(PHP_EOL, $logs));

        return true;
    }
}
