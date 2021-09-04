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

    protected function maybeDeleteCircleCIEnvironmentVariables(array $variables): bool
    {
        $circleci_api_key = static::circleCIApiKey();

        if ($circleci_api_key) {
            $circleci_project = $this->gitOriginProjectName();

            if (!$circleci_project) {
                return 1;
            }

            $circleci = static::circleCI($circleci_api_key, $circleci_project);

            $this->info('Checking CircleCI project is enabled...');

            if ($circleci->projectExists()) {
                $this->info('Checking CircleCI environment variables...');

                $circleci_existing_vars = $this->circleCIExistingEnvironmentVariablesAskDelete($circleci, $variables);

                if ($circleci_existing_vars === false) {
                    return false;
                }

                $this->info('Deleting CircleCI environment variables...');

                foreach ($circleci_existing_vars as $name) {
                    $circleci->deleteEnvironmentVariable($name);
                }c

                $this->info('Deleted CircleCi environment variables successfully');
            } else {
                $this->warn('CircleCI project was not found');
            }
        }

        return true;
    }

    protected function circleCIExistingEnvironmentVariablesAskDelete(Client $circleci, array $variables): array|false
    {
        $existing_circleci_vars = $circleci->listEnvironmentVariables();

        $exists = [];

        foreach ($existing_circleci_vars as $name => $value) {
            if (in_array($name, $variables)) {
                $exists[] = $name;

                $this->warn("CircleCI environment variable '$name' exists!");
            }
        }

        if ($exists && !$this->confirm('Would you like to delete these CircleCI environment variables and proceed?', false)) {
            return false;
        }

        return $exists;
    }
}
