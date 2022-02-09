<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\CircleCI\Client;

trait InteractsWithCircleCI
{
    use InteractsWithGitFiles;

    /**
     * The CircleCI client.
     *
     * @var Client|null
     */
    protected static ?Client $circleci_client = null;

    /**
     * Get the CircleCI client, instantiating it if not already done.
     *
     * @param string $api_key
     * @param string $project_name
     * @return Client|null
     */
    protected static function circleCI(string $api_key, string $project_name)
    {
        if (!static::$circleci_client) {
            static::$circleci_client = app(Client::class)->configure($api_key, $project_name);
        }

        return static::$circleci_client;
    }

    /**
     * Get the path for the CircleCI api key file.
     *
     * @return string
     */
    protected static function circleCIApiKeyFilePath(): string
    {
        return '.circleci/api-key.txt';
    }

    /**
     * Get the CircleCI api key from a file.
     *
     * @return string|false
     */
    protected static function circleCIApiKey(): string|false
    {
        if (!File::exists(base_path(static::circleCIApiKeyFilePath()))) {
            return false;
        }

        return trim(File::get(base_path(static::circleCIApiKeyFilePath()))) ?: false;
    }

    /**
     * Prompt to delete the specified environment variables and delete them if the user confirms.
     *
     * @param array $variables
     * @return bool
     * @throws \LaraSurf\LaraSurf\Exceptions\CircleCI\RequestFailedException
     */
    protected function maybeDeleteCircleCIEnvironmentVariables(array $variables): bool
    {
        $circleci_api_key = static::circleCIApiKey();

        if ($circleci_api_key) {
            $circleci_project = $this->gitOriginProjectName();

            if (!$circleci_project) {
                return false;
            }

            $circleci = static::circleCI($circleci_api_key, $circleci_project);

            $this->line('Checking CircleCI project is enabled...');

            if ($circleci->projectExists()) {
                $this->line('Checking CircleCI environment variables...');

                $circleci_existing_vars = $this->circleCIExistingEnvironmentVariablesAskDelete($circleci, $variables);

                if ($circleci_existing_vars === false) {
                    return false;
                }

                if ($circleci_existing_vars) {
                    $this->line('Deleting CircleCI environment variables...');

                    foreach ($circleci_existing_vars as $name) {
                        $circleci->deleteEnvironmentVariable($name);
                    }

                    $this->info('Deleted CircleCI environment variables successfully');
                }
            } else {
                $this->warn('CircleCI project was not found');
            }
        }

        return true;
    }

    /**
     * Confirm if a user wants to delete the specified environment variables.
     *
     * @param Client $circleci
     * @param array $variables
     * @return array|false
     * @throws \LaraSurf\LaraSurf\Exceptions\CircleCI\RequestFailedException
     */
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
