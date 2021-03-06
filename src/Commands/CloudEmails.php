<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\HasTimer;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Constants\Cloud;

class CloudEmails extends Command
{
    use HasSubCommands;
    use HasEnvironmentOption;
    use HasTimer;
    use InteractsWithAws;

    /**
     * The available subcommands to run.
     */
    const COMMAND_VERIFY_DOMAIN = 'verify-domain';
    const COMMAND_CHECK_VERIFICATION = 'check-verification';
    const COMMAND_ENABLE_SENDING = 'enable-sending';
    const COMMAND_CHECK_SENDING = 'check-sending';

    /**
     * @var string
     */
    protected $signature = 'larasurf:cloud-emails
                            {--environment=null : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'verify-domain\', \'check-verification\', \'enable-sending\', or \'check-sending\'}';

    /**
     * @var string
     */
    protected $description = 'Manage email sending capabilities in cloud environments';

    /**
     * A mapping of subcommands => method name to call.
     *
     * @var string[]
     */
    protected array $commands = [
        self::COMMAND_VERIFY_DOMAIN => 'handleVerifyDomain',
        self::COMMAND_CHECK_VERIFICATION => 'handleCheckVerification',
        self::COMMAND_ENABLE_SENDING => 'handleEnableSending',
        self::COMMAND_CHECK_SENDING => 'handleCheckSending',
    ];

    /**
     * Verifies the configured domain name for email sending, including DKIM.
     *
     * @return int
     * @throws \LaraSurf\LaraSurf\Exceptions\AwsClients\ExpectedArrayOfTypeException
     */
    protected function handleVerifyDomain()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $cloudformation = $this->awsCloudFormation($env);

        if (!$cloudformation->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $this->startTimer();

        $domain = $this->domain($env);

        if (!$domain) {
            return 1;
        }

        $this->line("Verifying email domain '$domain'...");

        $ses = $this->awsSes();

        $dns_record = $ses->verifyDomain($domain);

        $hosted_zone_id = $this->hostedZoneId($env);

        if (!$hosted_zone_id) {
            return 1;
        }

        $route53 = $this->awsRoute53();

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

        $this->line("Verifying email domain '$domain' for DKIM...");

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

    /**
     * Checks the configured domain for email sending, including DKIM.
     *
     * @return int
     */
    protected function handleCheckVerification()
    {
        $env = $this->environmentOption();

        if (!$env) {
            return 1;
        }

        $cloudformation = $this->awsCloudFormation($env);

        if (!$cloudformation->stackStatus()) {
            $this->error("Stack does not exist for the '$env' environment");

            return 1;
        }

        $domain = $this->domain($env);

        if (!$domain) {
            return 1;
        }

        $ses = $this->awsSes();

        $verified = $ses->checkDomainVerification($domain);

        $status = 0;

        if (!$verified) {
            $this->warn("Domain '$domain' is not verified for email sending");
            $status = 1;
        } else {
            $this->info("Domain '$domain' is verified for email sending");
        }

        $verified_dkim = $ses->checkDomainDkimVerification($domain);

        if (!$verified_dkim) {
            $this->warn("Domain '$domain' is not verified for DKIM");
            $status = 1;
        } else {
            $this->info("Domain '$domain' is verified for DKIM");
        }

        return $status;
    }

    /**
     * Requests to enable live email sending within SES for the AWS account.
     *
     * @return int
     */
    protected function handleEnableSending()
    {
        $cloudformation = $this->awsCloudFormation(Cloud::ENVIRONMENT_PRODUCTION);

        if (!$cloudformation->stackStatus()) {
            $this->error("Stack does not exist for the '" . Cloud::ENVIRONMENT_PRODUCTION . "' environment");

            return 1;
        }

        $ses = $this->awsSes();

        if ($ses->checkEmailSending()) {
            $this->warn('Live email sending is already enabled');

            return 0;
        }

        $domain = $this->domain(Cloud::ENVIRONMENT_PRODUCTION);

        if (!$domain) {
            return 1;
        }

        $description = $this->ask('Use Case Description', 'Send transactional emails from a Laravel application');
        $website = $this->ask('Website URL', "https://$domain");

        $ses->enableEmailSending($website, $description);

        $this->info('Requested live email sending successfully');
        $this->warn('Response from AWS may take up to 24 hours');

        return 0;
    }

    /**
     * Checks if live email sending for the AWS account is enabled within SES.
     *
     * @return int
     */
    protected function handleCheckSending()
    {
        if (!$this->awsSes()->checkEmailSending()) {
            $this->warn('Live email sending is not enabled');

            return 1;
        }

        $this->info('Live email sending is enabled');

        return 0;
    }

    /**
     * Returns the configured domain name for the given environment or false if not found.
     *
     * @param string $env
     * @return string|false
     */
    protected function domain(string $env): string|false
    {
        $domain = $this->awsCloudFormation($env)->stackOutput('DomainName');

        if (!$domain) {
            $this->error("Failed to find domain name for '$env' environment, has the stack been deployed?");

            return false;
        }

        return $domain;
    }

    /**
     * Returns the hosted zone ID for the given environment or false if not found.
     *
     * @param string $env
     * @return string|false
     */
    protected function hostedZoneId(string $env)
    {
        $hosted_zone_id = $this->awsCloudFormation($env)->stackOutput('HostedZoneId');

        if (!$hosted_zone_id) {
            $this->error("Failed to find Hosted Zone ID for '$env' environment, has the stack been deployed?");

            return false;
        }

        return $hosted_zone_id;
    }
}
