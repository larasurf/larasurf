<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Exceptions\Git\ParsingGitConfigFailedException;

trait InteractsWithGitFiles
{
    /**
     * The parsed git config.
     *
     * @var array|null
     */
    protected static ?array $git_config = null;

    /**
     * Get the parsed git config, parsing it if not already done.
     *
     * @return array
     * @throws ParsingGitConfigFailedException
     */
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

    /**
     * Get the git configuration file path.
     *
     * @return string
     */
    protected static function gitConfigFilePath(): string
    {
        return base_path('.git/config');
    }

    /**
     * Get the git head file path.
     *
     * @return string
     */
    protected static function gitHeadFilePath(): string
    {
        return base_path('.git/HEAD');
    }

    /**
     * Get the git ref heads directory path.
     *
     * @return string
     */
    protected static function gitRefHeadsPath(): string
    {
        return base_path('.git/refs/heads');
    }

    /**
     * Git a remote origin URL from the parsed git config.
     *
     * @param string $name
     * @return string|false
     * @throws ParsingGitConfigFailedException
     */
    protected static function gitRemoteUrl(string $name = 'origin'): string|false
    {
        return static::gitConfig()["remote $name"]['url'] ?? false;
    }

    /**
     * Git the origin project name or false.
     *
     * @return string|false
     * @throws ParsingGitConfigFailedException
     */
    protected function gitOriginProjectName()
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

    /**
     * Confirms the current git branch.
     *
     * @param string $branch
     * @return bool
     */
    protected function gitIsOnBranch(string $branch): bool
    {
        if (!File::exists(static::gitHeadFilePath())) {
            $this->error('Failed to find git HEAD, is this a git repository?');

            return false;
        }

        return trim(str_replace('ref: refs/heads/', '', File::get(static::gitHeadFilePath()))) === $branch;
    }

    /**
     * Gets the current git commit.
     *
     * @param string $branch
     * @return string
     */
    protected function gitCurrentCommit(string $branch): string
    {
        $path = static::gitRefHeadsPath() . '/' . $branch;

        if (!File::exists($path)) {
            $this->error('Failed to find current commit, is this a git repository?');

            return false;
        }

        return trim(File::get($path));
    }
}
