<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\HasTimer;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Constants\Cloud;

class CloudEmails extends Command
{
    use HasSubCommands;
    use HasEnvOption;
    use HasTimer;
    use InteractsWithAws;

    const COMMAND_VERIFY_DOMAIN = 'verify-domain';
    const COMMAND_CHECK_VERIFICATION = 'check-verification';
    const COMMAND_ENABLE_SENDING = 'enable-sending';
    const COMMAND_CHECK_SENDING = 'check-sending';

    protected $signature = 'larasurf:cloud-emails
                            {--env=null : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'verify-domain\', \'check-verification\', \'enable-sending\', or \'check-sending\'}';

    protected $description = 'Manage email sending capabilities in cloud environments';

    protected array $commands = [
        self::COMMAND_VERIFY_DOMAIN => 'handleVerifyDomain',
        self::COMMAND_CHECK_VERIFICATION => 'handleCheckVerification',
        self::COMMAND_ENABLE_SENDING => 'handleEnableSending',
        self::COMMAND_CHECK_SENDING => 'handleCheckSending',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    protected function handleVerifyDomain()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $this->startTimer();

        $domain = $this->domain($env);

        if (!$domain) {
            return 1;
        }

        $this->info("Verifying email domain '$domain'");

        $ses = static::awsSes();

        $dns_record = $ses->verifyDomain($domain);

        $hosted_zone_id = $this->hostedZoneId($env);

        $route53 = static::awsRoute53();

        $change_id = $route53->upsertDnsRecords($hosted_zone_id, [
           $dns_record,
        ]);

        $route53->waitForChange(
            $change_id,
            $this->getOutput(),
            'DNS record update is still pending, checking again soon...'
        );

        $ses->waitForDomainVerification(
            $domain,
            $this->getOutput(),
            'SES has not yet detected DNS record, checking again soon...'
        );

        $this->info('Email domain verified successfully');

        $this->info("Verifying email domain '$domain' for DKIM");

        $dns_records = $ses->verifyDomainDkim($domain);

        $change_id = $route53->upsertDnsRecords($hosted_zone_id, $dns_records);

        $route53->waitForChange(
            $change_id,
            $this->getOutput(),
            'DNS record update is still pending, checking again soon...'
        );

        $ses->waitForDomainDkimVerification(
            $domain,
            $this->getOutput(),
            'SES has not yet detected DNS record, checking again soon...'
        );

        $this->info('Email domain verified for DKIM successfully');

        $this->stopTimer();
        $this->displayTimeElapsed();

        return 0;
    }

    protected function handleCheckVerification()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $domain = $this->domain($env);

        if (!$domain) {
            return 1;
        }

        $ses = static::awsSes();

        $verified = $ses->checkDomainVerification($domain);

        if (!$verified) {
            $this->warn("Domain '$domain' is not verified for email sending");
        } else {
            $this->info("Domain '$domain' is verified for email sending");
        }

        $verified_dkim = $ses->checkDomainDkimVerification($domain);

        if (!$verified_dkim) {
            $this->warn("Domain '$domain' is not verified for DKIM");
        } else {
            $this->info("Domain '$domain' is verified for DKIM");
        }

        return 0;
    }

    protected function handleEnableSending()
    {
        $domain = $this->domain(Cloud::ENVIRONMENT_PRODUCTION);

        if (!$domain) {
            return 1;
        }

        $description = $this->ask('Use Case Description', 'Send transactional emails from a Laravel application');
        $website = $this->ask('Website URL', "https://$domain");

        static::awsSes()->enableEmailSending($website, $description);

        $this->info('Requested live email sending successfully.');
        $this->warn('Response from AWS may take up to 24 hours');

        return 0;
    }

    protected function handleCheckSending()
    {
        if (!static::awsSes()->checkEmailSending()) {
            $this->warn('Live email sending not enabled');

            return 0;
        }

        $this->info('Live email sending is enabled');

        return 0;
    }

    protected function domain(string $env): string|false
    {
        $domain = static::awsCloudFormation($env)->stackOutput('DomainName');

        if (!$domain) {
            $this->error("Failed to find domain name for '$env' environment");

            return false;
        }

        return $domain;
    }

    protected function hostedZoneId(string $env)
    {
        $hosted_zone_id = static::awsCloudFormation($env)->stackOutput('HostedZoneId');

        if (!$hosted_zone_id) {
            $this->error("Failed to find Hosted Zone ID for '$env' environment");

            return false;
        }

        return $hosted_zone_id;
    }
}
