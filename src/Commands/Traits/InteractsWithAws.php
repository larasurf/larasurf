<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Aws\Acm\AcmClient;
use Aws\CloudFormation\CloudFormationClient;
use Aws\Credentials\Credentials;
use Aws\Exception\CredentialsException;
use Aws\Route53\Route53Client;
use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
use Aws\Ssm\SsmClient;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\File;

trait InteractsWithAws
{
    protected static function laraSurfAwsProfileCredentialsProvider($profile_name): callable
    {
        return function() use ($profile_name) {
            $credentials_file_path = static::getAwsCredentialsFilePath();

            if (!File::exists($credentials_file_path)) {
                return new RejectedPromise(new CredentialsException("File does not exist: $credentials_file_path"));
            }

            $credentials = parse_ini_file($credentials_file_path, true);

            if (!isset($credentials[$profile_name])) {
                return new RejectedPromise(new CredentialsException("Profile '$profile_name' does not exist in $credentials_file_path"));
            }

            if (empty($credentials[$profile_name]['aws_access_key_id'])) {
                return new RejectedPromise(new CredentialsException("Profile '$profile_name' does not contain 'aws_access_key_id'"));
            }

            if (empty($credentials[$profile_name]['aws_secret_access_key'])) {
                return new RejectedPromise(new CredentialsException("Profile '$profile_name' does not contain 'aws_secret_access_key'"));
            }

            return Create::promiseFor(
                new Credentials($credentials[$profile_name]['aws_access_key_id'], $credentials[$profile_name]['aws_secret_access_key'])
            );
        };
    }

    protected static function getAwsCredentialsFilePath()
    {
        return '/larasurf/aws/credentials';
    }


    protected function getSsmClient($config, $environment)
    {
        return new SsmClient([
            'version' => 'latest',
            'region' => $config['cloud-environments'][$environment]['aws-region'],
            'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
        ]);
    }

    protected function getSsmParameterPath($config, $environment, $parameter = null)
    {
        $parameter = $parameter ?? '';

        return '/' . $config['project-name'] . '/' . $environment . '/' . $parameter;
    }

    protected function getCloudFormationClient($config, $environment) {
        return new CloudFormationClient([
            'version' => 'latest',
            'region' => $config['cloud-environments'][$environment]['aws-region'],
            'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
        ]);
    }

    protected function getCloudFormationStackName($config, $environment)
    {
        return "{$config['project-name']}-{$environment}";
    }

    protected function getRoute53Client($config, $environment) {
        return new Route53Client([
            'version' => 'latest',
            'region' => $config['cloud-environments'][$environment]['aws-region'],
            'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
        ]);
    }

    protected function getAcmClient($config, $environment) {
        return new AcmClient([
            'version' => 'latest',
            'region' => $config['cloud-environments'][$environment]['aws-region'],
            'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
        ]);
    }

    protected function getSesClient($config, $environment) {
        return new SesClient([
            'version' => 'latest',
            'region' => $config['cloud-environments'][$environment]['aws-region'],
            'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
        ]);
    }

    protected function getSesV2Client($config, $environment) {
        return new SesV2Client([
            'version' => 'latest',
            'region' => $config['cloud-environments'][$environment]['aws-region'],
            'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
        ]);
    }
}
