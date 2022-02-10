<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudArtisan extends Command
{
    use InteractsWithAws;
    use HasEnvironmentOption;

    /**
     * @var string
     */
    protected $signature = 'larasurf:cloud-artisan
                            {cmd : The full artisan command to run, in quotes}
                            {--environment= : The environment to run the artisan command on; \'stage\' or \'production\'}';

    /**
     * @var string
     */
    protected $description = 'Run artisan commands on cloud environments';

    /**
     * Runs an arbitrary artisan command on a cloud environment by launching an ECS task
     * using the Artisan task definition. Fetches the logs from CloudWatch Logs and displays them after running.
     *
     * @return int
     */
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

        $command = ['php', 'artisan', ...explode(' ', $artisan_command)];

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

        $this->line('Running ECS task...');

        $ecs = $this->awsEcs($env, $aws_region);
        $task_arn = $ecs->runTask($outputs['ContainerClusterArn'], $security_groups, $subnets, $command, $outputs['ArtisanTaskDefinitionArn']);

        if (!$task_arn) {
            $this->error('Failed to start ECS task to run artisan command');

            return 1;
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

            return 1;
        }

        $logs = $this->awsCloudWatchLogs($env, $aws_region)->listLogStream(
            $log_group,
            'artisan/artisan/' . Str::afterLast($task_arn, '/'),
        );

        if (!$logs) {
            $this->error("Failed to get events from artisan log group for '$env' environment");

            return 1;
        }

        $this->info('Task output:');
        $this->line(implode(PHP_EOL, $logs));

        return 0;
    }
}
