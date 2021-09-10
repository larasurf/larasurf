<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudArtisan extends Command
{
    use InteractsWithAws;
    use HasEnvironmentOption;

    protected $signature = 'larasurf:cloud-artisan
                            {command : The full artisan command to run, in quotes}
                            {--environment : The environment to run the artisan command on; \'stage\' or \'production\'}';

    protected $description = 'Run artisan commands on cloud environments';

    public function handle()
    {
        $command = ['php', 'artisan', ...explode(' ', $this->argument('command'))];

        $env = $this->environmentOption();

        $aws_region = static::larasurfConfig()->get("environments.$env.aws-region");

        if (!$aws_region) {
            $this->error("AWS region is not set for the '$env' environment; create image repositories first");

            return 1;
        }

        $cloudformation = $this->awsCloudFormation($env, $aws_region);

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

            return 1;
        }

        $this->info('Started ECS task to run artisan command successfully');

        return 0;
    }
}
