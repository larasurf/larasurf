<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Infra extends Command
{
    use InteractsWithLaraSurfConfig;
    use HasSubCommand;

    const COMMAND_CREATE = 'create';
    const COMMAND_DESTROY = 'destroy';

    protected $signature = 'larasurf:env {--env=} {command}';

    protected $description = 'Manipulate the infrastructure for an upstream environment';

    protected $commands = [
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_DESTROY => 'handleDestroy',
    ];

    public function handle()
    {
        if (!$this->validateEnvOption()) {
            return;
        }

        if (!$this->validateCommandArgument()) {
            return;
        }

        $this->runSubCommand();
    }

    protected function handleCreate()
    {
        
    }
}
