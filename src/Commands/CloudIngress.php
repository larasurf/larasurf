<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudIngress extends Command
{
    use HasSubCommands;
    use HasEnvironmentOption;
    use InteractsWithAws;

    /**
     * The available subcommands to run.
     */
    const COMMAND_ALLOW = 'allow';
    const COMMAND_REVOKE = 'revoke';
    const COMMAND_LIST = 'list';

    /**
     * @var string
     */
    protected $signature = 'larasurf:cloud-ingress
                            {--environment=null : The environment: \'stage\' or \'production\'}
                            {--type=null : The resource type for ingress: \'application\' or \'database\'}
                            {--source=null : The source to allow ingress from: \'me\', \'public\', or an IP (X.X.X.X)}
                            {subcommand : The subcommand to run: \'allow\', \'revoke\', or \'list\'}';

    /**
     * @var string
     */
    protected $description = 'Manage ingress to the application or database in cloud environments';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_ALLOW => 'handleAllow',
        self::COMMAND_REVOKE => 'handleRevoke',
        self::COMMAND_LIST => 'handleList',
    ];

    /**
     * Allow ingress for the specified type from the specified source for the specified environment.
     *
     * @return int
     */
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

        if (!$this->awsCloudFormation($env)->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $prefix_list_id = $this->prefixListId($env, $type);

        if (!$prefix_list_id) {
            return 1;
        }

        $ec2 = $this->awsEc2($env);
        $ec2->allowIpPrefixList($prefix_list_id, $source);
        $ec2->waitForPrefixListUpdate(
            $prefix_list_id,
            $this->getOutput(),
            'Prefix List update is still pending, checking again soon...'
        );

        $this->info('Prefix List updated successfully');

        return 0;
    }

    /**
     * Revoke ingress for the specified type from the specified source for the specified environment.
     *
     * @return int
     */
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

        if (!$this->awsCloudFormation($env)->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $prefix_list_id = $this->prefixListId($env, $type);

        if (!$prefix_list_id) {
            return 1;
        }

        $ec2 = $this->awsEc2($env);
        $ec2->revokeIpPrefixList($prefix_list_id, $source);
        $ec2->waitForPrefixListUpdate(
            $prefix_list_id,
            $this->getOutput(),
            'Prefix List update is still pending, checking again soon...'
        );

        $this->info('Prefix List updated successfully');

        return 0;
    }

    /**
     * List ingress for the specified type for the specified environment.
     *
     * @return int
     */
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

        if (!$this->awsCloudFormation($env)->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $prefix_list_id = $this->prefixListId($env, $type);

        if (!$prefix_list_id) {
            return 1;
        }

        $results = $this->awsEc2($env)->listIpsPrefixList($prefix_list_id);

        foreach ($results as $result) {
            $cidr = $result->getCidr();
            $description = $result->getDescription();

            $this->line("<info>$cidr:</info> $description");
        }

        return 0;
    }

    /**
     * Returns the valid type option or false.
     *
     * @return string|false
     */
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

    /**
     * Returns the source option or false if not found.
     *
     * @return string|false
     */
    protected function sourceOption(): string|false
    {
        $source = $this->option('source');

        if (!$source || $source === 'null') {
            $this->error('The --source option is required for this subcommand');

            return false;
        }

        return $source;
    }

    /**
     * Returns the prefix list ID for the specified environment and type or false if not found.
     *
     * @param string $env
     * @param string $type
     * @return string|false
     */
    protected function prefixListId(string $env, string $type): string|false
    {
        $key = $type === 'database' ? 'DBAdminAccessPrefixListId' : 'AppAccessPrefixListId';

        $prefix_list_id = $this->awsCloudFormation($env)->stackOutput($key);

        if (!$prefix_list_id) {
            $this->error("Failed to find $type Prefix List ID for '$env' environment");

            return false;
        }

        return $prefix_list_id;
    }
}
