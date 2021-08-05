<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Facades\File;

trait InteractsWithLaraSurfConfig
{
    protected $valid_aws_regions = [
        'us-east-1', // todo: update
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

        if (!isset($json['schema-version'])) {
            $this->error('Key \'schema-version\' not found in larasurf.json');

            return false;
        }

        if ($json['schema-version'] === 1) {
            return $this->getValidLarasurfConfigVersion1($json);
        }

        $this->error('Invalid larasurf.json schema version found');

        return false;
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
        if ($config['schema-version'] === 1) {
            if (isset($config['cloud-environments'][$environment])) {
                return true;
            }
        }

        $this->error("Environment '$environment' does not exist in larasurf.json");

        return false;
    }
}
