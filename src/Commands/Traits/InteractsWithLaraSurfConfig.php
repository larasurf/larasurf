<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Facades\File;

trait InteractsWithLaraSurfConfig
{
    protected $valid_aws_regions = [
        'us-east-1', // todo: update
    ];

    protected $valid_db_types = [
        'db.t2.micro',
        'db.t2.small',
        'db.t2.medium',
        'db.m5.large',
        'db.m5.xlarge',
    ];

    protected $minimum_db_storage_gb = 20;

    protected $maxmium_db_storage_gb = 70368; // 64 tebibytes

    protected $valid_cache_types = [
        'cache.m5.large',
        'cache.m5.xlarge',
        'cache.m5.2xlarge',
        'cache.m5.4xlarge',
        'cache.m5.12xlarge',
        'cache.m5.24xlarge',
        'cache.m4.large',
        'cache.m4.xlarge',
        'cache.m4.2xlarge',
        'cache.m4.4xlarge',
        'cache.m4.10xlarge',
        'cache.t2.micro',
        'cache.t2.small',
        'cache.t2.medium',
    ];

    protected function getValidLarasurfConfig()
    {
        if (!File::exists(base_path('larasurf.json'))) {
            $this->error('larasurf.json does not exist');

            return false;
        }

        $config = File::get(base_path('larasurf.json'));

        $json = json_decode($config, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error parsing larasurf.json');
            $this->error(json_last_error_msg());

            return false;
        }

        return $this->getValidLarasurfConfigVersion1($json);
    }

    protected function getValidLarasurfConfigVersion1(array $json)
    {
        if (!isset($json['project-name'])) {
            $this->error('Key \'project-name\' not found in larasurf.json');

            return false;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $json['project-name'])) {
            $this->error('Invalid project name in larasurf.json');

            return false;
        }

        if (!isset($json['aws-profile'])) {
            $this->error('Key \'aws-profile\' not found in larasurf.json');

            return false;
        }

        if (isset($json['cloud-environments'])) {
            $environments = array_keys($json['cloud-environments']);

            foreach ($environments as $environment) {
                if (!in_array($environment, $this->valid_environments)) {
                    $this->error("Invalid environment '$environment' in larasurf.json");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['db-type'])) {
                    $this->error("Key 'db-type' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!in_array($json['cloud-environments'][$environment]['db-type'], $this->valid_db_types)) {
                    $this->error("Invalid database type for environment '$environment'");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['cache-type'])) {
                    $this->error("Key 'cache-type' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!in_array($json['cloud-environments'][$environment]['cache-type'], $this->valid_cache_types)) {
                    $this->error("Invalid cache type for environment '$environment'");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['db-storage-gb'])) {
                    $this->error("Key 'db-storage-gb' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!is_int($json['cloud-environments'][$environment]['db-storage-gb'])) {
                    $this->error("Invalid value for database storage GB for environment '$environment'");

                    return false;
                }

                if ($json['cloud-environments'][$environment]['db-storage-gb'] > $this->maxmium_db_storage_gb) {
                    $this->error("Database storage GB must be less than or equal to {$this->maxmium_db_storage_gb} for environment '$environment'");

                    return false;
                }

                if ($json['cloud-environments'][$environment]['db-storage-gb'] < $this->minimum_db_storage_gb) {
                    $this->error("Database storage GB must be greater than or equal to {$this->minimum_db_storage_gb} for environment '$environment'");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['aws-region'])) {
                    $this->error("Key 'aws-region' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!in_array($json['cloud-environments'][$environment]['aws-region'], $this->valid_aws_regions)) {
                    $this->error("Invalid AWS region for environment '$environment'");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['aws-certificate-arn'])) {
                    $this->error("Key 'aws-certificate-arn' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['aws-hosted-zone-id'])) {
                    $this->error("Key 'aws-hosted-zone-id' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['domain'])) {
                    $this->error("Key 'domain' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['stack-deployed'])) {
                    $this->error("Key 'stack-deployed' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!is_bool($json['cloud-environments'][$environment]['stack-deployed'])) {
                    $this->error("Key 'stack-deployed' for environment '$environment' must be a boolean");

                    return false;
                }

                if (!isset($json['cloud-environments'][$environment]['variables'])) {
                    $this->error("Key 'variables' not found for environment '$environment' in larasurf.json");

                    return false;
                }

                if (!is_array($json['cloud-environments'][$environment]['variables'])) {
                    $this->error("Key 'variables' for environment '$environment' in larasurf.json must be an array");

                    return false;
                }

                foreach($json['cloud-environments'][$environment]['variables'] as $variable) {
                    if (!preg_match('/^[A-Z0-9_]+$/', $variable)) {
                        $this->error("Invalid environment variable name '$variable' for environment '$environment' in larasurf.json");

                        return false;
                    }
                }
            }
        }

        return $json;
    }

    protected function writeLaraSurfConfig(array $config)
    {
        $json = json_encode($config, JSON_PRETTY_PRINT);

        $success = File::put(base_path('larasurf.json'), $json . PHP_EOL);

        if (!$success) {
            $this->error('Failed to write to larasurf.json');

            return false;
        } else {
            $this->info('File larasurf.json updated successfully');

            return true;
        }
    }

    protected function validateEnvironmentExistsInConfig(array $config, string $environment)
    {
        if (isset($config['cloud-environments'][$environment])) {
            return true;
        }

        return false;
    }

    protected function validateDomainInConfig($config, $environment)
    {
        if (empty($config['cloud-environments'][$environment]['domain'])) {
            $this->error("Domain not set for environment '$environment' in larasurf.json");

            return false;
        }

        return true;
    }
}
