<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Config extends Command
{
    use InteractsWithLaraSurfConfig;
    use HasSubCommand;

    const COMMAND_AWS_PROFILE = 'aws-profile';

    protected $signature = 'larasurf:config {command} {arg1?}';

    protected $description = 'Configure LaraSurf';

    protected $commands = [
        self::COMMAND_AWS_PROFILE => 'handleAwsProfile',
    ];

    public function handle()
    {
        if (!$this->validateCommandArgument()) {
            return;
        }

        $this->runCommand();
    }

    protected function handleAwsProfile()
    {
        $profile = $this->argument('arg1');

        if (!$profile) {
            $this->error('A profile name must be specified');

            return;
        }

        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return;
        }

        if ($config['schema-version'] === 1) {
            $config['aws-profile'] = $profile;
            $json = json_encode($config, JSON_PRETTY_PRINT);

            $success = File::put($json, app_path('larasurf.json'));

            if (!$success) {
                $this->error('Failed to write larasurf.json');
            } else {
                $this->info('File larasurf.json updated successfully');
            }
        }
    }
}
