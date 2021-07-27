<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

trait HasSubCommand
{
    protected $commands = [];

    protected function validateCommandArgument()
    {
        $command = $this->argument('command');

        if (!in_array($command, array_keys($this->commands))) {
            $this->error('Invalid command specified');

            return false;
        }

        return true;
    }

    protected function runSubCommand()
    {
        $command = $this->argument('command');

        ([$this, $this->commands[$command]])();
    }
}
