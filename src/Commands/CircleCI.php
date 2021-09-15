<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\CircleCI\Client;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithCircleCI;

class CircleCI extends Command
{
    use HasSubCommands;
    use InteractsWithCircleCI;

    const COMMAND_SET_API_KEY = 'set-api-key';
    const COMMAND_CLEAR_API_KEY = 'clear-api-key';

    protected $signature = 'larasurf:circleci
                            {subcommand : The subcommand to run: \'set-api-key\' or \'clear-api-key\'}';

    protected $description = 'Manage CircleCI projects';

    protected array $commands = [
        self::COMMAND_SET_API_KEY => 'handleSetApiKey',
        self::COMMAND_CLEAR_API_KEY => 'handleDeleteApiKey',
    ];

    protected function handleSetApiKey()
    {
        $origin = $this->gitOriginProjectName();

        if (!$origin) {
            return 1;
        }

        $api_token = $this->secret('Enter your CircleCI API token:');

        $this->line('Verifying API token...');
        
        $client = new Client($api_token, $origin);

        if (!$client->checkApiKey()) {
            $this->error('Failed to verify API key');
            
            return 1;
        }
        
        $this->info('Verified API key successfully');

        $path = static::circleCIApiKeyFilePath();

        if (!File::put($path, $api_token)) {
            $this->error("Failed to write to file: $path");

            return 1;
        }

        $this->info("Updated file '$path' successfully");

        return 0;
    }

    protected function handleDeleteApiToken()
    {
        $path = static::circleCIApiKeyFilePath();

        if (!File::exists($path)) {
            $this->error("No file exists at: $path");

            return 1;
        }

        if (!File::delete($path)) {
            $this->error("Failed to write to file: $path");

            return 1;
        }

        $this->info("Deleted file '$path' successfully");

        return 0;
    }
}
