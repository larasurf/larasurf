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

    /**
     * The available subcommands to run.
     */
    const COMMAND_SET_API_KEY = 'set-api-key';
    const COMMAND_CLEAR_API_KEY = 'clear-api-key';

    /**
     * @var string
     */
    protected $signature = 'larasurf:circleci
                            {subcommand : The subcommand to run: \'set-api-key\' or \'clear-api-key\'}';

    /**
     * @var string
     */
    protected $description = 'Manage CircleCI projects';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_SET_API_KEY => 'handleSetApiKey',
        self::COMMAND_CLEAR_API_KEY => 'handleClearApiKey',
    ];

    /**
     * Set the CircleCI API key in a file (not checked into source control) for reuse in subsequent commands.
     *
     * @return int
     */
    protected function handleSetApiKey()
    {
        $origin = $this->gitOriginProjectName();

        if (!$origin) {
            return 1;
        }

        $api_token = $this->secret('Enter your CircleCI API token:');

        $this->line('Verifying API token...');

        // new CircleCI API client
        $client = app(Client::class)->configure($api_token, $origin);

        if (!$client->checkApiKey()) {
            $this->error('Failed to verify API key');

            return 1;
        }

        $this->info('Verified API key successfully');

        $path = static::circleCIApiKeyFilePath();

        if (!File::put(base_path($path), $api_token)) {
            $this->error("Failed to write to file: $path");

            return 1;
        }

        $this->info("Updated file '$path' successfully");

        return 0;
    }

    /**
     * Clear the CircleCI API key stored in a file.
     *
     * @return int
     */
    protected function handleClearApiKey()
    {
        $path = static::circleCIApiKeyFilePath();

        if (!File::exists(base_path($path))) {
            $this->error("No file exists at: $path");

            return 1;
        }

        if (!File::delete(base_path($path))) {
            $this->error("Failed to write to file: $path");

            return 1;
        }

        $this->info("Deleted file '$path' successfully");

        return 0;
    }
}
