<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\Ec2Client;
use LaraSurf\LaraSurf\AwsClients\EcrClient;
use LaraSurf\LaraSurf\AwsClients\EcsClient;
use LaraSurf\LaraSurf\AwsClients\IamClient;
use LaraSurf\LaraSurf\AwsClients\RdsClient;
use LaraSurf\LaraSurf\AwsClients\Route53Client;
use LaraSurf\LaraSurf\AwsClients\SesClient;
use LaraSurf\LaraSurf\AwsClients\SsmClient;
use LaraSurf\LaraSurf\Constants\Cloud;

trait InteractsWithAws
{
    use InteractsWithLaraSurfConfig;

    protected function awsAcm(string $environment = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments($environment);

        return new AcmClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsCloudFormation(string $environment = null, string $aws_region = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments($environment, $aws_region);

        return new CloudFormationClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsEc2(string $environment = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments($environment);

        return new Ec2Client($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsRoute53()
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments();

        return new Route53Client($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsSes()
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments();

        return new SesClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsSsm(string $environment = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments($environment);

        return new SsmClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsRds(string $environment = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments($environment);

        return new RdsClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsEcr(string $environment = null, string $aws_region = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments($environment, $aws_region);

        return new EcrClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsIam()
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments();

        return new IamClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsEcs(string $environment = null, string $aws_region = null)
    {
        [$project_name, $project_id, $aws_profile, $aws_region, $environment] = $this->awsClientArguments($environment, $aws_region);

        return new EcsClient($project_name, $project_id, $aws_profile, $aws_region, $environment);
    }

    protected function awsClientArguments(string $environment = null, string $aws_region = null): array
    {
        $project_name = static::larasurfConfig()->get('project-name');
        $project_id = static::larasurfConfig()->get('project-id');
        $aws_profile = static::larasurfConfig()->get('aws-profile');
        $aws_region = $aws_region ?: static::larasurfConfig()->get("environments.$environment.aws-region") ?: Cloud::AWS_REGION_US_EAST_1;

        return [
            $project_name,
            $project_id,
            $aws_profile,
            $aws_region,
            $environment,
        ];
    }

    protected function awsEcrRepositoryName(string $environment, string $type): string
    {
        return static::larasurfConfig()->get('project-name') . '-' . static::larasurfConfig()->get('project-id') . "/$environment/$type";
    }
}
