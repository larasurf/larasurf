<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithGitFiles;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;
use LaraSurf\LaraSurf\Constants\Cloud;

class ConfigureNewEnvironments extends Command
{
    use HasSubCommands;
    use InteractsWithLaraSurfConfig;
    use InteractsWithGitFiles;

    const ENVIRONMENTS_STAGE_PRODUCTION = Cloud::ENVIRONMENT_STAGE . '-' . Cloud::ENVIRONMENT_PRODUCTION;

    /**
     * The available subcommands to run.
     */
    const COMMAND_VALIDATE_NEW_ENVIRONMENTS = 'validate-new-environments';
    const COMMAND_GET_NEW_BRANCHES = 'get-new-branches';
    const COMMAND_MODIFY_LARASURF_CONFIG = 'modify-larasurf-config';

    /**
     * @var string
     */
    protected $signature = 'larasurf:configure-new-environments
                            {subcommand : The subcommand to run: \'get\' or \'set\'}
                            {--environments= : The environments: \'stage\', \'production\', or \'stage-production\'}';

    /**
     * @var string
     */
    protected $description = 'Configure new environments for the application';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_VALIDATE_NEW_ENVIRONMENTS => 'handleValidateNewEnvironments',
        self::COMMAND_GET_NEW_BRANCHES => 'handleGetNewBranches',
        self::COMMAND_MODIFY_LARASURF_CONFIG => 'handleModifyLaraSurfConfig',
    ];

    /**
     * Validate the specified new environments.
     *
     * @return int
     */
    protected function handleValidateNewEnvironments()
    {
        if (!$this->gitIsOnBranch('main')) {
            $this->error('Checkout the main branch before running this command.');

            return 1;
        }

        $envs = $this->validEnvironmentsOption();

        return $envs ? 0 : 1;
    }

    /**
     * Get the names of the new branches to create.
     *
     * @return int
     */
    protected function handleGetNewBranches()
    {
        $envs = $this->validEnvironmentsOption();

        if (!$envs) {
            return 1;
        }

        $new_branches = [
            self::ENVIRONMENTS_STAGE_PRODUCTION => 'stage-develop',
            Cloud::ENVIRONMENT_STAGE => 'stage',
            Cloud::ENVIRONMENT_PRODUCTION => 'develop',
        ][$envs];

        $this->getOutput()->write($new_branches);

        return 0;
    }

    /**
     * Modify the LaraSurf configuration file with the new environments and publish files if needed.
     *
     * @return int
     */
    protected function handleModifyLaraSurfConfig()
    {
        $envs = $this->validEnvironmentsOption();

        if (!$envs) {
            return 1;
        }

        switch ($envs) {
            case self::ENVIRONMENTS_STAGE_PRODUCTION: {
                static::larasurfConfig()->set('environments.stage', null);
                static::larasurfConfig()->set('environments.production', null);

                break;
            }
            case Cloud::ENVIRONMENT_PRODUCTION: {
                static::larasurfConfig()->set('environments.production', null);

                break;
            }
            case Cloud::ENVIRONMENT_STAGE: {
                static::larasurfConfig()->set('environments.stage', null);

                break;
            }
        }

        if(!static::larasurfConfig()->write()) {
            $this->error('Failed to update LaraSurf configuration');

            return 1;
        }

        $this->info("File '" . static::laraSurfConfigFilePath() ."' updated successfully");

        $circleci_config_file = base_path('.circleci/config.yml');

        if (File::exists($circleci_config_file)) {
            File::delete($circleci_config_file);
        }

        Artisan::call('larasurf:publish --circleci --cloudformation --proxies');

        return 0;
    }

    /**
     * Gets a valid --environments option value.
     *
     * @return string|false
     */
    protected function validEnvironmentsOption()
    {
        $envs = $this->option('environments');

        if (!$envs || $envs === 'null') {
            $this->error('The --environments option is required for this subcommand');

            return false;
        }

        switch ($envs) {
            // should only have production environment
            case Cloud::ENVIRONMENT_STAGE: {
                if (static::larasurfConfig()->exists('environments.stage')) {
                    $this->error('The stage environment has already been configured');

                    return false;
                }

                if (!static::larasurfConfig()->exists('environments.production')) {
                    $this->error('A production environment must be configured first (or configure them both at the same time)');
                    return false;
                }

                break;
            }
            // should have neither environment
            case Cloud::ENVIRONMENT_PRODUCTION:
            case self::ENVIRONMENTS_STAGE_PRODUCTION: {
                if (static::larasurfConfig()->exists('environments.stage') || static::larasurfConfig()->exists('environments.production')) {
                    $this->error('This environment(s) have already been configured');

                    return false;
                }

                break;
            }
            default: {
                $this->error('Invalid --environments option given');

                return false;
            }
        }

        return $envs;
    }
}
