<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudVars extends Command
{
    use HasSubCommands;
    use HasEnvOption;
    use InteractsWithAws;

    const COMMAND_EXISTS = 'exists';
    const COMMAND_GET = 'get';
    const COMMAND_PUT = 'put';
    const COMMAND_DELETE = 'delete';
    const COMMAND_LIST = 'list';

    protected $signature = 'larasurf:cloud-vars
                            {--env=null : The environment: \'stage\' or \'production\'}
                            {--key=null : The variable key, required for \'exists\', \'get\', \'put\', and \'delete\'}
                            {--value=null : The variable value, required for\'put\'}
                            {--values : Specifies the value of the variables should be output when using the \'list\' subcommand}
                            {subcommand : The subcommand to run: \'exists\', \'get\', \'put\', \'delete\', or \'list\'}';

    protected $description = 'Manage application environment variables in cloud environments';

    protected array $commands = [
        self::COMMAND_EXISTS => 'handleExists',
        self::COMMAND_GET => 'handleGet',
        self::COMMAND_PUT => 'handlePut',
        self::COMMAND_DELETE => 'handleDelete',
        self::COMMAND_LIST => 'handleList',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    public function handleExists()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $key = $this->keyOption();

        if (!$key) {
            return 1;
        }

        if(static::awsSsm($env)->getParameter($key) === false) {
            $this->warn("Variable '$key' does not exist in the '$env' environment");
        } else {
            $this->info("Variable '$key' exists for in '$env' environment");
        }

        return 0;
    }

    public function handleGet()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $key = $this->keyOption();

        if (!$key) {
            return 1;
        }

        $value = static::awsSsm($env)->getParameter($key);

        if ($value === false) {
            $this->warn("Variable '$key' does not exist in the '$env' environment");
        }

        $this->getOutput()->writeln("<info>$key:</info> $value");

        return 0;
    }

    public function handlePut()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $key = $this->keyOption();

        if (!$key) {
            return 1;
        }

        $value = $this->valueOption();

        if (!$value) {
            return 1;
        }

        static::awsSsm($env)->putParameter($key, $value);

        $this->info("Variable '$key' set in the '$env' environment successfully");

        return 0;
    }

    public function handleDelete()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $key = $this->keyOption();

        if (!$key) {
            return 1;
        }

        static::awsSsm($env)->deleteParameter($key);

        return 0;
    }

    public function handleList()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $show_values = $this->valuesOption();

        $values = static::awsSsm($env)->listParameters($show_values);

        if ($show_values) {
            foreach ($values as $key => $value) {
                $this->getOutput()->writeln("<info>$key:</info> $value");
            }
        } else {
            foreach ($values as $key) {
                $this->info($key);
            }
        }

        return 1;
    }

    protected function keyOption(): string|false
    {
        $key = $this->option('key');

        if (!$key || $key === 'null') {
            $this->error('The --key option is required for this subcommand');

            return false;
        }

        if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
            $this->error('Invalid --key option given');

            return false;
        }

        return $key;
    }

    protected function valueOption(): string|false
    {
        $value = $this->option('value');

        if (!$value || $value === 'null') {
            $this->error('The --value option is required for this subcommand');

            return false;
        }

        return $value;
    }

    protected function valuesOption(): bool
    {
        return (bool) $this->option('values');
    }
}
