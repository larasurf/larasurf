<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\Ec2Client;
use LaraSurf\LaraSurf\AwsClients\Route53Client;
use LaraSurf\LaraSurf\AwsClients\SesClient;
use LaraSurf\LaraSurf\AwsClients\SsmClient;
use LaraSurf\LaraSurf\Constants\Cloud;

trait InteractsWithAws
{
    use InteractsWithConfig;

    protected static function awsAcm(string $environment = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = static::clientArguments($environment);

        return new AcmClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected static function awsCloudFormation(string $environment = null, string $aws_region = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = static::clientArguments($environment, $aws_region);

        return new CloudFormationClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected static function awsEc2(string $environment = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = static::clientArguments($environment);

        return new Ec2Client($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected static function awsRoute53()
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = static::clientArguments();

        return new Route53Client($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected static function awsSes()
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = static::clientArguments();

        return new SesClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected static function awsSsm(string $environment = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = static::clientArguments($environment);

        return new SsmClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected static function clientArguments(string $environment = null, string $aws_region = null): array
    {
        $project_name = static::config()->get('project-name');
        $project_id = static::config()->get('project-id');
        $aws_profile = static::config()->get('aws-profile');
        $aws_region = $aws_region ?: static::config()->get("environments.$environment.aws-region") ?: Cloud::AWS_REGION_US_EAST_1;

        return [
            $project_name,
            $project_id,
            $aws_profile,
            $aws_region,
            $environment,
        ];
    }
}
