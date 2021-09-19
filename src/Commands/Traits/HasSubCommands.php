<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

trait HasSubCommands
{
    /**
     * Handle the main command, running the subcommand.
     *
     * @return int
     */
    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    /**
     * Validates the specified subcommand argument exists.
     *
     * @return bool
     */
    protected function validateSubCommandArgument()
    {
        $command = $this->argument('subcommand');

        if (!in_array($command, array_keys($this->commands))) {
            $this->error('Invalid subcommand specified');

            return false;
        }

        return true;
    }

    /**
     * Runs the specified subcommand.
     *
     * @return int
     */
    protected function runSubCommand()
    {
        $command = $this->argument('subcommand');

        return ([$this, $this->commands[$command]])();
    }
}
