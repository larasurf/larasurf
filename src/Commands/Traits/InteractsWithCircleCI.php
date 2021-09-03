<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\CircleCI\Client;

trait InteractsWithCircleCI
{
    use InteractsWithGitConfig;

    protected static ?Client $circleci_client = null;

    protected static function circleCI(string $api_key, string $project_name)
    {
        if (!static::$circleci_client) {
            static::$circleci_client = new Client($api_key, $project_name);
        }

        return static::$circleci_client;
    }

    protected static function circleCIApiKeyFilePath(): string
    {
        return '.circleci/api-key.txt';
    }

    protected static function circleCIApiKey(): string|false
    {
        return trim(File::get(base_path(static::circleCIApiKeyFilePath()))) ?: false;
    }

    protected function circleCIExistingEnvironmentVariablesAskDelete(Client $circleci): array|false
    {
        $existing_circleci_vars = $circleci->listEnvironmentVariables();

        $exists = [];

        foreach ($existing_circleci_vars as $name => $value) {
            if (in_array($name, [
                'AWS_ACCESS_KEY_ID',
                'AWS_SECRET_ACCESS_KEY',
                'AWS_REGION',
                'AWS_ECR_URL_APPLICATION',
                'AWS_ECR_URL_WEBSERVER',
            ])) {
                $exists[] = $name;

                $this->warn("CircleCI environment variable '$name' already exists!");
            }
        }

        if ($exists && !$this->ask('Would you like to delete these CircleCI environment variables and proceed?', false)) {
            return false;
        }

        return $exists;
    }
}
