<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentArgument;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Infra extends Command
{
    use InteractsWithLaraSurfConfig;
    use HasEnvironmentArgument;
    use HasSubCommand;

    const COMMAND_CREATE = 'create';
    const COMMAND_DESTROY = 'destroy';

    protected $signature = 'larasurf:infra {subcommand} {environment}';

    protected $description = 'Manipulate the infrastructure for an upstream environment';

    protected $commands = [
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_DESTROY => 'handleDestroy',
    ];

    public function handle()
    {
        if (!$this->validateEnvironmentArgument()) {
            return;
        }

        if (!$this->validateSubCommandArgument()) {
            return;
        }

        $this->runSubCommand();
    }

    protected function handleCreate()
    {
        $this->info('ToDo: handle create');
    }

    protected function handleDestroy()
    {
        $this->info('ToDo: handle destroy');
    }
}
