<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

trait HasSubCommands
{
    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    protected function validateSubCommandArgument()
    {
        $command = $this->argument('subcommand');

        if (!in_array($command, array_keys($this->commands))) {
            $this->error('Invalid subcommand specified');

            return false;
        }

        return true;
    }

    protected function runSubCommand()
    {
        $command = $this->argument('subcommand');

        return ([$this, $this->commands[$command]])();
    }
}
