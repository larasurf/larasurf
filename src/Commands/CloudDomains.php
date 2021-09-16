<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;

class CloudDomains extends Command
{
    use HasSubCommands;
    use InteractsWithAws;

    /**
     * The available subcommands to run.
     */
    const COMMAND_HOSTED_ZONE_EXISTS = 'hosted-zone-exists';
    const COMMAND_CREATE_HOSTED_ZONE = 'create-hosted-zone';
    const COMMAND_NAMESERVERS = 'nameservers';

    /**
     * @var string
     */
    protected $signature = 'larasurf:cloud-domains
                            {--domain= : The top level domain name}
                            {subcommand : The subcommand to run: \'hosted-zone-exists\', \'create-hosted-zone\', or \'nameservers\'}';

    /**
     * @var string
     */
    protected $description = 'Manage hosted zones and nameserver DNS records in the cloud';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_HOSTED_ZONE_EXISTS => 'handleHostedZoneExists',
        self::COMMAND_CREATE_HOSTED_ZONE => 'handleCreateHostedZone',
        self::COMMAND_NAMESERVERS => 'handleNameServers',
    ];

    /**
     * Determine if a hosted zone exists by domain name.
     *
     * @return int
     */
    public function handleHostedZoneExists()
    {
        $domain = $this->domainOption();

        $root_domain = $this->rootDomainFromFullDomain($domain);

        $id = $this->awsRoute53()->hostedZoneIdFromRootDomain($root_domain);

        if (!$id) {
            $this->warn("Hosted zone not found for domain '$domain'");

            return 1;
        }

        $this->line("<info>Hosted zone exists with ID:</info> $id");

        return 0;
    }

    /**
     * Create a hosted zone by domain name.
     */
    public function handleCreateHostedZone()
    {
        $domain = $this->domainOption();

        $root_domain = $this->rootDomainFromFullDomain($domain);

        $id = $this->awsRoute53()->createHostedZone($root_domain);

        $this->line("<info>Hosted zone created with ID:</info> $id");

        return 0;
    }

    /**
     * List the name servers for a hosted zone by domain name.
     */
    public function handleNameServers()
    {
        $domain = $this->domainOption();

        $route53 = $this->awsRoute53();

        $root_domain = $this->rootDomainFromFullDomain($domain);

        $id = $route53->hostedZoneIdFromRootDomain($root_domain);

        if (!$id) {
            $this->error("Hosted zone not found for domain '$domain'");

            return 1;
        }

        $nameservers = $route53->hostedZoneNameServers($id);

        $this->line(implode(PHP_EOL, $nameservers));

        return 0;
    }

    /**
     * @return string
     */
    protected function domainOption(): string
    {
        return $this->option('domain');
    }
}
