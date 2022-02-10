<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\AwsClients\CloudFormationClient;
use LaraSurf\LaraSurf\AwsClients\CloudWatchLogsClient;
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

    /**
     * @param string|null $environment
     * @return AcmClient
     */
    protected function awsAcm(string $environment = null): AcmClient
    {
        return app(AcmClient::class)->configure(...$this->awsClientArguments($environment));
    }

    /**
     * @param string|null $environment
     * @param string|null $aws_region
     * @return CloudFormationClient
     */
    protected function awsCloudFormation(string $environment = null, string $aws_region = null): CloudFormationClient
    {
        return app(CloudFormationClient::class)->configure(...$this->awsClientArguments($environment, $aws_region));
    }

    /**
     * @param string|null $environment
     * @param string|null $aws_region
     * @return CloudWatchLogsClient
     */
    protected function awsCloudWatchLogs(string $environment = null, string $aws_region = null): CloudWatchLogsClient
    {
        return app(CloudWatchLogsClient::class)->configure(...$this->awsClientArguments($environment, $aws_region));
    }

    /**
     * @param string|null $environment
     * @return Ec2Client
     */
    protected function awsEc2(string $environment = null): Ec2Client
    {
        return app(Ec2Client::class)->configure(...$this->awsClientArguments($environment));
    }

    /**
     * @return Route53Client
     */
    protected function awsRoute53(): Route53Client
    {
        return app(Route53Client::class)->configure(...$this->awsClientArguments());
    }

    /**
     * @return SesClient
     */
    protected function awsSes(): SesClient
    {
        return app(SesClient::class)->configure(...$this->awsClientArguments());
    }

    /**
     * @param string|null $environment
     * @return SsmClient
     */
    protected function awsSsm(string $environment = null): SsmClient
    {
        return app(SsmClient::class)->configure(...$this->awsClientArguments($environment));
    }

    /**
     * @param string|null $environment
     * @return RdsClient
     */
    protected function awsRds(string $environment = null): RdsClient
    {
        return app(RdsClient::class)->configure(...$this->awsClientArguments($environment));
    }

    /**
     * @param string|null $environment
     * @param string|null $aws_region
     * @return EcrClient
     */
    protected function awsEcr(string $environment = null, string $aws_region = null): EcrClient
    {
        return app(EcrClient::class)->configure(...$this->awsClientArguments($environment, $aws_region));
    }

    /**
     * @return IamClient
     */
    protected function awsIam(): IamClient
    {
        return app(IamClient::class)->configure(...$this->awsClientArguments());
    }

    /**
     * @param string|null $environment
     * @param string|null $aws_region
     * @return EcsClient
     */
    protected function awsEcs(string $environment = null, string $aws_region = null): EcsClient
    {
        return app(EcsClient::class)->configure(...$this->awsClientArguments($environment, $aws_region));
    }

    /**
     * @param string|null $environment
     * @param string|null $aws_region
     * @return array
     */
    protected function awsClientArguments(string $environment = null, string $aws_region = null): array
    {
        $project_name = static::larasurfConfig()->get('project-name');
        $project_id = static::larasurfConfig()->get('project-id');
        $aws_profile = static::larasurfConfig()->get('aws-profile');
        $aws_region = $aws_region ?: static::larasurfConfig()->get("environments.$environment.aws-region") ?: Cloud::AWS_REGION_US_EAST_1;

        return compact('project_name', 'project_id', 'aws_profile', 'aws_region', 'environment');
    }

    /**
     * @param string $environment
     * @param string $type
     * @return string
     */
    protected function awsEcrRepositoryName(string $environment, string $type): string
    {
        return static::larasurfConfig()->get('project-name') . '-' . static::larasurfConfig()->get('project-id') . "/$environment/$type";
    }

    /**
     * @param string $full_domain
     * @return string
     */
    protected function rootDomainFromFullDomain(string $full_domain): string
    {
        $suffix = Str::afterLast($full_domain, '.');
        $domain_length = strlen($full_domain) - strlen($suffix) - 1;
        $domain = substr($full_domain, 0, $domain_length);

        if (Str::contains($domain, '.')) {
            $domain = Str::afterLast($domain, '.');
        }

        $domain .= '.' . $suffix;

        return $domain;
    }
}
