<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudIngress extends Command
{
    use HasSubCommands;
    use HasEnvOption;
    use InteractsWithAws;

    const COMMAND_ALLOW = 'allow';
    const COMMAND_REVOKE = 'revoke';
    const COMMAND_LIST = 'list';

    protected $signature = 'larasurf:cloud-ingress
                            {--environment=null : The environment: \'stage\' or \'production\'}
                            {--type=null : The resource type for ingress: \'application\' or \'database\'}
                            {--source=null : The source to allow ingress from: \'me\', \'public\', or an IP (X.X.X.X)}
                            {subcommand : The subcommand to run: \'allow\', \'revoke\', or \'list\'}';

    protected $description = 'Manage ingress to the application or database in cloud environments';

    protected array $commands = [
        self::COMMAND_ALLOW => 'handleAllow',
        self::COMMAND_REVOKE => 'handleRevoke',
        self::COMMAND_LIST => 'handleList',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    protected function handleAllow()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $type = $this->typeOption();

        if (!$type) {
            return 1;
        }

        $source = $this->sourceOption();

        if (!$source) {
            return 1;
        }

        $prefix_list_id = $this->prefixListId($env, $type);

        if (!$prefix_list_id) {
            return 1;
        }

        $ec2 = static::awsEc2($env);
        $ec2->allowIpPrefixList($prefix_list_id, $source);
        $ec2->waitForPrefixListUpdate(
            $prefix_list_id,
            $this->getOutput(),
            'Prefix List update is still pending, checking again soon...'
        );

        $this->info('Prefix List updated successfully');

        return 0;
    }

    protected function handleRevoke()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $type = $this->typeOption();

        if (!$type) {
            return 1;
        }

        $source = $this->sourceOption();

        if (!$source) {
            return 1;
        }

        $prefix_list_id = $this->prefixListId($env, $type);

        if (!$prefix_list_id) {
            return 1;
        }

        $ec2 = static::awsEc2($env);
        $ec2->revokeIpPrefixList($prefix_list_id, $source);
        $ec2->waitForPrefixListUpdate(
            $prefix_list_id,
            $this->getOutput(),
            'Prefix List update is still pending, checking again soon...'
        );

        $this->info('Prefix List updated successfully');

        return 0;
    }

    public function handleList()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $type = $this->typeOption();

        if (!$type) {
            return 1;
        }

        $prefix_list_id = $this->prefixListId($env, $type);

        if (!$prefix_list_id) {
            return 1;
        }

        $results = static::awsEc2($env)->listIpsPrefixList($prefix_list_id);

        foreach ($results as $result) {
            $cidr = $result->getCidr();
            $description = $result->getDescription();

            $this->getOutput()->writeln("<info>$cidr:</info> $description");
        }

        return 0;
    }

    protected function typeOption(): string|false
    {
        $type = $this->option('type');

        if (!$type || $type === 'null') {
            $this->error('The --type option is required for this subcommand');

            return false;
        }

        if (!in_array($type, ['application', 'database'])) {
            $this->error('Invalid --type option given');

            return false;
        }

        return $type;
    }

    protected function sourceOption(): string|false
    {
        $source = $this->option('source');

        if (!$source || $source === 'null') {
            $this->error('The --source option is required for this subcommand');

            return false;
        }

        return $source;
    }

    protected function prefixListId(string $env, string $type): string|false
    {
        $key = $type === 'database' ? 'DBAdminAccessPrefixListId' : 'AppAccessPrefixListId';

        $prefix_list_id = static::awsCloudFormation($env)->stackOutput($key);

        if (!$prefix_list_id) {
            $this->error("Failed to find database Prefix List ID for '$env' environment");

            return false;
        }

        return $prefix_list_id;
    }
}
