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

    protected static function circleCiApiKeyFilePath(): string
    {
        return '.circleci/api-key.txt';
    }

    protected static function circleCIApiKey(): string|false
    {
        return trim(File::get(base_path(static::circleCiApiKeyFilePath()))) ?: false;
    }
}
