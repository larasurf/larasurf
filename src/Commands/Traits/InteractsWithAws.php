<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Aws\Acm\AcmClient;
use Aws\CloudFormation\CloudFormationClient;
use Aws\Credentials\Credentials;
use Aws\Exception\CredentialsException;
use Aws\Route53\Route53Client;
use Aws\Ses\SesClient;
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
        if ($config['schema-version'] === 1) {
            return new SsmClient([
                'version' => 'latest',
                'region' => $config['cloud-environments'][$environment]['aws-region'],
                'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
            ]);
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }

    protected function getSsmParameterPath($config, $environment, $parameter = null)
    {
        $parameter = $parameter ?? '';

        if ($config['schema-version'] === 1) {
            return '/' . $config['project-name'] . '/' . $environment . '/' . $parameter;
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }

    protected function getCloudFormationClient($config, $environment) {
        if ($config['schema-version'] === 1) {
            return new CloudFormationClient([
                'version' => 'latest',
                'region' => $config['cloud-environments'][$environment]['aws-region'],
                'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
            ]);
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }

    protected function getCloudFormationStackName($config, $environment)
    {
        if ($config['schema-version'] === 1) {
            return "{$config['project-name']}-{$environment}";
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }

    protected function getRoute53Client($config, $environment) {
        if ($config['schema-version'] === 1) {
            return new Route53Client([
                'version' => 'latest',
                'region' => $config['cloud-environments'][$environment]['aws-region'],
                'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
            ]);
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }

    protected function getAcmClient($config, $environment) {
        if ($config['schema-version'] === 1) {
            return new AcmClient([
                'version' => 'latest',
                'region' => $config['cloud-environments'][$environment]['aws-region'],
                'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
            ]);
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }

    protected function getSesClient($config, $environment) {
        if ($config['schema-version'] === 1) {
            return new SesClient([
                'version' => 'latest',
                'region' => $config['cloud-environments'][$environment]['aws-region'],
                'credentials' => self::laraSurfAwsProfileCredentialsProvider($config['aws-profile']),
            ]);
        }

        $this->error('Unsupported schema version in larasurf.json');

        return false;
    }
}
