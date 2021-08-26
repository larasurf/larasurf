<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\AwsClients\AcmClient;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvOption;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommands;
use LaraSurf\LaraSurf\Commands\Traits\HasTimer;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithConfig;
use LaraSurf\LaraSurf\Constants\Cloud;

class CloudStacks extends Command
{
    use HasSubCommands;
    use HasEnvOption;
    use HasTimer;
    use InteractsWithConfig;
    use InteractsWithAws;

    const COMMAND_STATUS = 'status';
    const COMMAND_CREATE = 'create';
    const COMMAND_DELETE = 'delete';

    protected $signature = 'larasurf:cloud-vars
                            {--env=null : The environment: \'stage\' or \'production\'}
                            {subcommand : The subcommand to run: \'exists\', \'create\', or \'delete\'}';

    protected $description = 'Manage application environment variables in cloud environments';

    protected array $commands = [
        self::COMMAND_STATUS => 'handleStatus',
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_DELETE => 'handleDelete',
    ];

    public function handle()
    {
        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    public function handleStatus()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $status = static::awsCloudFormation($env)->stackStatus();

        if (!$status) {
            $this->warn("Stack for '$env' environment does not exist");
        } else {
            $this->getOutput()->writeln("<info>Status:</info> $status");
        }

        return 0;
    }

    public function handleCreate()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        $this->info('Valid database instance types:');
        $this->getOutput()->writeln(implode(PHP_EOL, Cloud::DB_INSTANCE_TYPES));

        do {
            $db_instance_type = $this->ask('Database instance type?', 'db.t2.small');

            $valid = in_array($db_instance_type, Cloud::DB_INSTANCE_TYPES);

            if (!$valid) {
                $this->error('Invalid database instance type');
            }
        } while (!$valid);

        $this->getOutput()->writeln('<info>Minimum database storage (GB):</info> ' . Cloud::DB_STORAGE_MIN_GB);
        $this->getOutput()->writeln('<info>Maximum database storage (GB):</info> ' . Cloud::DB_STORAGE_MAX_GB);

        do {
            $db_storage = (int) $this->ask('Database storage (GB)?', Cloud::DB_STORAGE_MIN_GB);
            $valid = $db_storage < Cloud::DB_STORAGE_MAX_GB && $db_storage > Cloud::DB_STORAGE_MIN_GB;

            if (!$valid) {
                $this->error('Invalid database storage size');
            }
        } while (!$valid);

        $domain = $this->ask('Fully qualified domain name?');

        $route53 = static::awsRoute53();

        $hosted_zone_id = $route53->hostedZoneIdFromDomain($domain);

        if (!$hosted_zone_id) {
            $this->error("Hosted zone for domain '$domain' could not be found");

            if (!$this->ask("Would you like to create a hosted zone for domain '$domain'?", false)) {
                return 0;
            }

            $hosted_zone_id = $route53->createHostedZone($domain);
        }

        if ($this->confirm('Is there a preexisting ACM certificate you\'d like to use?', false)) {
            do {
                $acm_arn = $this->ask('ACM certificate ARN?');
                $valid = preg_match('/^arn:aws:acm:.+:certificate/.+$/', $acm_arn);

                if (!$valid) {
                    $this->error('Invalid ACM certificate ARN');
                }
            } while (!$valid);
        } else {
            $this->info('Creating ACM certificate');

            $acm = static::awsAcm($env);
            $acm_arn = null;

            $dns_record = $acm->requestCertificate(
                $acm_arn,
                $domain,
                AcmClient::VALIDATION_METHOD_DNS,
                $this->getOutput()
            );

            $this->info('Verifying ACM certificate via DNS record');

            $changed_id = $route53->upsertDnsRecords($hosted_zone_id, [$dns_record]);

            $route53->waitForChange(
                $changed_id,
                $this->getOutput(),
                'DNS record update is still pending, checking again soon...'
            );

            $acm->waitForPendingValidation(
                $acm_arn,
                $this->getOutput(),
                'ACM certificate validation is still pending, checking again soon...'
            );
        }

        $this->startTimer();

        $this->info("Creating stack for '$env' environment");

        $db_username = Str::random(random_int(16, 32));
        $db_password = Str::random(random_int(32, 40));

        $cloudformation = static::awsCloudFormation($env);

        $cloudformation->createStack(
            $domain,
            $acm_arn,
            $db_storage,
            $db_instance_type,
            $db_username,
            $db_password
        );

        $result = $cloudformation->waitForStackUpdate(
            $this->getOutput(),
            'CloudFormation stack is still being created, checking again soon...'
        );

        if (!$result['success']) {
            $this->error("Stack creation failed with status '{$result['status']}'");
        } else {
            $this->info("Stack creation completed successfully");
        }

        $this->stopTimer();
        $this->displayTimeElapsed();

        return 0;
    }

    public function handleDelete()
    {
        $env = $this->envOption();

        if (!$env) {
            return 1;
        }

        if (!$this->confirm("Are you sure you want to delete the stack for the '$env' environment?", false)) {
            return 0;
        }

        static::awsCloudFormation($env)->deleteStack();

        return 0;
    }
}
