<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Exceptions\Git\ParsingGitConfigFailedException;

trait InteractsWithGitConfig
{
    protected static ?array $git_config = null;

    protected static function gitConfig(): array
    {
        $config_path = static::gitConfigFilePath();

        if (!static::$git_config) {
            $config = parse_ini_file($config_path, true);

            if (!$config) {
                throw new ParsingGitConfigFailedException($config_path);
            }

            static::$git_config = $config;
        }

        return static::$git_config;
    }

    protected static function gitConfigFilePath()
    {
        return '.git/config';
    }

    protected static function gitRemoteUrl(string $name = 'origin'): string|false
    {
        return static::gitConfig()["remote \"{$name}\""]['url'] ?? false;
    }

    protected function gitOriginUrl()
    {
        $url = static::gitRemoteUrl();

        if (!$url) {
            $this->error('Failed to find origin remote URL from git config, is git origin set?');

            return false;
        }

        $origin = str_replace('git@github.com:', '', str_replace('.git', '', $url));

        if (Str::contains($origin, ':')) {
            $this->error('Unrecognized remote URL in git config');

            return false;
        }

        return $origin;
    }
}
