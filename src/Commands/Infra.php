<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Commands\Traits\HasEnvironmentArgument;
use LaraSurf\LaraSurf\Commands\Traits\HasSubCommand;
use LaraSurf\LaraSurf\Commands\Traits\HasValidEnvironments;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithAws;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Infra extends Command
{
    use InteractsWithLaraSurfConfig;
    use InteractsWithAws;
    use HasValidEnvironments;
    use HasEnvironmentArgument;
    use HasSubCommand;

    const COMMAND_CREATE = 'create';
    const COMMAND_DESTROY = 'destroy';
    const COMMAND_ISSUE_CERTIFICATE = 'issue-certificate';
    const COMMAND_CHECK_CERTIFICATE = 'check-certificate';
    const COMMAND_DELETE_CERTIFICATE = 'delete-certificate';
    const COMMAND_VERIFY_EMAIL_DOMAIN = 'verify-email-domain';
    const COMMAND_DELETE_EMAIL_DOMAIN = 'delete-email-domain';
    const COMMAND_VERIFY_EMAIL_DOMAIN_DKIM = 'verify-email-domain-dkim';
    const COMMAND_ENABLE_EMAIL_SENDING = 'enable-email-sending';
    const COMMAND_CHECK_EMAIL_SENDING = 'check-email-sending';
    // todo: command to add/remove IPs from SG for stage access

    protected $signature = 'larasurf:infra {subcommand} {environment}';

    protected $description = 'Manipulate the infrastructure for a cloud environment';

    protected $commands = [
        self::COMMAND_CREATE => 'handleCreate',
        self::COMMAND_DESTROY => 'handleDestroy',
        self::COMMAND_ISSUE_CERTIFICATE => 'handleIssueCertificate',
        self::COMMAND_CHECK_CERTIFICATE => 'handleCheckCertificate',
        self::COMMAND_DELETE_CERTIFICATE => 'handleDeleteCertificate',
        self::COMMAND_VERIFY_EMAIL_DOMAIN => 'handleVerifyEmailDomain',
        self::COMMAND_VERIFY_EMAIL_DOMAIN_DKIM => 'handleVerifyEmailDomainDkim',
        self::COMMAND_DELETE_EMAIL_DOMAIN => 'handleDeleteEmailDomain',
        self::COMMAND_ENABLE_EMAIL_SENDING => 'handleEnableEmailSending',
        self::COMMAND_CHECK_EMAIL_SENDING => 'handleCheckEmailSending',
    ];

    public function handle()
    {
        if (!$this->validateEnvironmentArgument()) {
            return 1;
        }

        if (!$this->validateSubCommandArgument()) {
            return 1;
        }

        return $this->runSubCommand();
    }

    protected function handleCreate()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $success = $this->ensureHostedZoneIdInConfig($config, $environment);

        if (!$success) {
            return 1;
        }

        $success = $this->createStack($config, $environment);

        if (!$success) {
            return 1;
        }

        $config['cloud-environments'][$environment]['stack-deployed'] = true;

        $success = $this->writeLaraSurfConfig($config);

        if (!$success) {
            return 1;
        }

        $success = $this->afterCreateStackUpdateParameters($config, $environment);

        if (!$success) {
            return 1;
        }

        return 0;
    }

    protected function handleDestroy()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getCloudFormationClient($config, $environment);

        $stack_name = $this->getCloudFormationStackName($config, $environment);

        if ($this->confirm("Are you sure you want to destroy the '$environment' environment?")) {
            $client->deleteStack([
                'StackName' => $stack_name,
            ]);

            $this->info('Stack deletion initiated');
            $this->line("See https://console.aws.amazon.com/cloudformation/home?region={$config['cloud-environments'][$environment]['aws-region']} for stack deletion status");
        }

        $config['cloud-environments'][$environment]['stack-deployed'] = false;

        $success = $this->writeLaraSurfConfig($config);

        if (!$success) {
            return 1;
        }

        return 0;
    }

    protected function handleIssueCertificate()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if ($config['cloud-environments'][$environment]['aws-certificate-arn'] &&
            !$this->confirm('AWS Certificate ARN exists in larasurf.json. Issue a new certificate and overwrite?', false)
        ) {
            return 0;
        }

        $success = $this->ensureHostedZoneIdInConfig($config, $environment);

        if (!$success) {
            return 1;
        }

        $client = $this->getAcmClient($config, $environment);

        $this->info("Requesting new certificate for domain '{$config['cloud-environments'][$environment]['domain']}'");

        $result = $client->requestCertificate([
            'DomainName' => $config['cloud-environments'][$environment]['domain'],
            'Tags' => [
                [
                    'Key' => 'Project',
                    'Value' => $config['project-name'],
                ],
                [
                    'Key' => 'Environment',
                    'Value' => $environment,
                ],
            ],
            'ValidationMethod' => 'DNS',
        ]);

        $this->info('New certificate requested successfully');

        $arn = $result['CertificateArn'];

        $config['cloud-environments'][$environment]['aws-certificate-arn'] = $arn;

        if (!$this->writeLaraSurfConfig($config)) {
            return 1;
        }

        $finished = false;
        $success = false;
        $record_name = null;
        $record_value = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $client->describeCertificate([
                'CertificateArn' => $config['cloud-environments'][$environment]['aws-certificate-arn'],
            ]);

            if (isset($result['Certificate']['DomainValidationOptions'])) {
                $record_name = $result['Certificate']['DomainValidationOptions'][0]['ResourceRecord']['Name'] ?? '';
                $record_value = $result['Certificate']['DomainValidationOptions'][0]['ResourceRecord']['Value'] ?? '';
                $finished = !empty($record_name) && !empty($record_value);

                if (!$finished) {
                    $this->line('Certificate verification DNS records aren\'t avaiable yet, checking again in 10 seconds...');
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds...');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('Failed to get certificate verification DNS records within 30 minutes');

            return 1;
        }

        $this->info('Creating certificate verification DNS records');

        $route53_client = $this->getRoute53Client($config, $environment);

        $dns_result = $route53_client->changeResourceRecordSets([
            'ChangeBatch' => [
                'Changes' => [
                    [
                        'Action' => 'UPSERT',
                        'ResourceRecordSet' => [
                            'Name' => $record_name,
                            'ResourceRecords' => [
                                [
                                    'Value' => $record_value,
                                ],
                            ],
                            'TTL' => 300,
                            'Type' => 'CNAME',
                        ],
                    ],
                ],
                'Comment' => 'Created by LaraSurf',
            ],
            'HostedZoneId' => $config['cloud-environments'][$environment]['aws-hosted-zone-id'],
        ]);

        $finished = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $route53_client->getChange([
                'Id' => $dns_result['ChangeInfo']['Id'],
            ]);

            if (isset($result['ChangeInfo']['Status'])) {
                $status = $result['ChangeInfo']['Status'];
                $finished = $status === 'INSYNC';

                if (!$finished) {
                    $this->line("DNS record change is not yet in sync (status: $status), checking again in 10 seconds...");
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('DNS record change set failed to be in sync within 30 minutes');

            return 1;
        }

        $this->info('DNS record change set is now in sync');

        $finished = false;
        $success = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $client->describeCertificate([
                'CertificateArn' => $config['cloud-environments'][$environment]['aws-certificate-arn'],
            ]);

            if (isset($result['Certificate']['Status'])) {
                $status = $result['Certificate']['Status'];
                $finished = $status !== 'PENDING_VALIDATION';

                if ($finished) {
                    $success = $status === 'ISSUED';
                } else {
                    $this->line('Certificate verification is still pending, checking again in 10 seconds...');
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('Certificate failed to verify within 30 minutes');

            return 1;
        } else if (!$success) {
            $this->error("Certificate verification failed with status: '$status'");
            $this->error("See https://console.aws.amazon.com/acm/home?region={$config['cloud-environments'][$environment]['aws-region']} for more information");

            return 1;
        }

        $this->info('Certificate verified successfully');

        return 0;
    }

    protected function handleCheckCertificate()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (empty($config['cloud-environments'][$environment]['aws-certificate-arn'])) {
            $this->error('AWS Certificate ARN not set in larasurf.json');

            return 1;
        }

        $client = $this->getAcmClient($config, $environment);

        $result = $client->describeCertificate([
            'CertificateArn' => $config['cloud-environments'][$environment]['aws-certificate-arn'],
        ]);

        $status = $result['Certificate']['Status'] ?? 'UNKNOWN';

        $this->line($status);

        return 0;
    }

    protected function handleDeleteCertificate()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (empty($config['cloud-environments'][$environment]['aws-certificate-arn'])) {
            $this->error('AWS Certificate ARN not set in larasurf.json');

            return 1;
        }

        if (!$this->confirm("Are you sure you want to delete the certificate for the '$environment' environment?")) {
            return 0;
        }

        $client = $this->getAcmClient($config, $environment);

        $client->deleteCertificate([
            'CertificateArn' => $config['cloud-environments'][$environment]['aws-certificate-arn'],
        ]);

        $this->info('Certificate deleted successfully');

        $config['cloud-environments'][$environment]['aws-certificate-arn'] = false;

        if (!$this->writeLaraSurfConfig($config)) {
            return 1;
        }

        return 0;
    }

    protected function handleVerifyEmailDomain()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (!$this->validateDomainInConfig($config, $environment)) {
            return 1;
        }

        if (!$this->ensureHostedZoneIdInConfig($config, $environment)) {
            return 1;
        }

        $ses_client = $this->getSesClient($config, $environment);

        $result = $ses_client->verifyDomainIdentity([
            'Domain' => $config['cloud-environments'][$environment]['domain'],
        ]);

        $verification_token = $result['VerificationToken'];

        $this->info("Created pending domain identity for domain '{$config['cloud-environments'][$environment]['domain']}' successfully");

        $route53_client = $this->getRoute53Client($config, $environment);

        $dns_result = $route53_client->changeResourceRecordSets([
            'ChangeBatch' => [
                'Changes' => [
                    [
                        'Action' => 'UPSERT',
                        'ResourceRecordSet' => [
                            'Name' => "_amazonses.{$config['cloud-environments'][$environment]['domain']}",
                            'ResourceRecords' => [
                                [
                                    'Value' => "\"$verification_token\"",
                                ],
                            ],
                            'TTL' => 300,
                            'Type' => 'TXT',
                        ],
                    ],
                ],
                'Comment' => 'Created by LaraSurf',
            ],
            'HostedZoneId' => $config['cloud-environments'][$environment]['aws-hosted-zone-id'],
        ]);

        $finished = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $route53_client->getChange([
                'Id' => $dns_result['ChangeInfo']['Id'],
            ]);

            if (isset($result['ChangeInfo']['Status'])) {
                $status = $result['ChangeInfo']['Status'];
                $finished = $status === 'INSYNC';

                if (!$finished) {
                    $this->line("DNS record change is not yet in sync (status: $status), checking again in 10 seconds...");
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('DNS record change set failed to be in sync within 30 minutes');

            return false;
        }

        $this->info('DNS record change set is now in sync');

        $finished = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $ses_client->getIdentityVerificationAttributes([
                'Identities' => [
                    $config['cloud-environments'][$environment]['domain'],
                ],
            ]);

            if (isset($result['VerificationAttributes'][$config['cloud-environments'][$environment]['domain']])) {
                $status = $result['VerificationAttributes'][$config['cloud-environments'][$environment]['domain']]['VerificationStatus'];
                $finished = $status === 'Success';

                if (!$finished) {
                    $this->line('SES has not detected the DNS records yet, checking again in 10 seconds...');
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('SES has failed to detect the DNS records within 30 minutes');

            return false;
        }

        $this->info("Email for domain '{$config['cloud-environments'][$environment]['domain']}' verified successfully");

        return 0;
    }

    protected function handleVerifyEmailDomainDkim()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (!$this->validateDomainInConfig($config, $environment)) {
            return 1;
        }

        if (!$this->ensureHostedZoneIdInConfig($config, $environment)) {
            return 1;
        }

        $ses_client = $this->getSesClient($config, $environment);

        $result = $ses_client->verifyDomainDkim([
            'Domain' => $config['cloud-environments'][$environment]['domain'],
        ]);

        $tokens = $result['DkimTokens'];

        $this->info("Created pending DKIM verification for domain '{$config['cloud-environments'][$environment]['domain']}' successfully");

        $route53_client = $this->getRoute53Client($config, $environment);

        $args = [
            'ChangeBatch' => [
                'Changes' => [],
                'Comment' => 'Created by LaraSurf',
            ],
            'HostedZoneId' => $config['cloud-environments'][$environment]['aws-hosted-zone-id'],
        ];

        foreach ($tokens as $token) {
            $args['ChangeBatch']['Changes'][] = [
                'Action' => 'UPSERT',
                'ResourceRecordSet' => [
                    'Name' => "$token._domainkey.{$config['cloud-environments'][$environment]['domain']}",
                    'ResourceRecords' => [
                        [
                            'Value' => "$token.dkim.amazonses.com",
                        ],
                    ],
                    'TTL' => 300,
                    'Type' => 'CNAME',
                ],
            ];
        }

        $dns_result = $route53_client->changeResourceRecordSets($args);

        $finished = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $route53_client->getChange([
                'Id' => $dns_result['ChangeInfo']['Id'],
            ]);

            if (isset($result['ChangeInfo']['Status'])) {
                $status = $result['ChangeInfo']['Status'];
                $finished = $status === 'INSYNC';

                if (!$finished) {
                    $this->line('DNS record change is not yet in sync, checking again in 10 seconds...');
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('DNS record change set failed to be in sync within 30 minutes');

            return false;
        }

        $this->info('DNS record change set is now in sync');

        $finished = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $ses_client->getIdentityDkimAttributes([
                'Identities' => [
                    $config['cloud-environments'][$environment]['domain'],
                ],
            ]);

            if (isset($result['DkimAttributes'][$config['cloud-environments'][$environment]['domain']])) {
                $status = $result['DkimAttributes'][$config['cloud-environments'][$environment]['domain']]['DkimVerificationStatus'];
                $finished = $status === 'Success';

                if (!$finished) {
                    $this->line('SES has not detected the DNS records yet, checking again in 10 seconds...');
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('SES has failed to detect the DNS records within 30 minutes');

            return false;
        }

        $this->info("Email DKIM for domain '{$config['cloud-environments'][$environment]['domain']} verified successfully'");

        return 0;
    }

    protected function handleDeleteEmailDomain()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (!$this->validateDomainInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getSesClient($config, $environment);

        $client->deleteIdentity([
            'Identity' => $config['cloud-environments'][$environment]['domain'],
        ]);

        $this->info("Email identity for domain '{$config['cloud-environments'][$environment]['domain']}' deleted successfully");

        return 0;
    }

    protected function handleEnableEmailSending()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (!$this->validateDomainInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getSesV2Client($config, $environment);

        $result = $client->getAccount();

        $enabled = $result['ProductionAccessEnabled'] ?? false;

        if ($enabled) {
            $this->info('Email sending is already enabled');

            return 1;
        }

        $additional_email = $this->ask('Additional email to be contacted:', 'none');

        $description = $this->ask('Use Case Description:', 'Send transactional emails from a Laravel application');

        $args = [
            'MailType' => 'TRANSACTIONAL',
            'ProductionAccessEnabled' => true,
            'UseCaseDescription' => $description,
            'WebsiteURL' => $config['cloud-environments'][$environment]['domain'],
        ];

        if ($additional_email && strtolower($additional_email) !== 'none') {
            $args['AdditionalContactEmailAddresses'] = [$additional_email];
        }

        $client->putAccountDetails($args);

        $this->info('Requested email sending access from AWS successfully');

        $this->line('Please allow up to 24 hours for a response');

        return 0;
    }

    protected function handleCheckEmailSending()
    {
        $config = $this->getValidLarasurfConfig();

        if (!$config) {
            return 1;
        }

        $environment = $this->argument('environment');

        if (!$this->validateEnvironmentExistsInConfig($config, $environment)) {
            return 1;
        }

        if (!$this->validateDomainInConfig($config, $environment)) {
            return 1;
        }

        $client = $this->getSesV2Client($config, $environment);

        $result = $client->getAccount();

        $enabled = $result['ProductionAccessEnabled'] ?? false;

        if (!$enabled) {
            $this->line('false');

            return 1;
        }

        $this->line('true');

        return 0;
    }

    protected function ensureHostedZoneIdInConfig(&$config, $environment)
    {
        if (!$this->validateDomainInConfig($config, $environment)) {

            return false;
        }

        if (empty($config['cloud-environments'][$environment]['aws-hosted-zone-id'])) {
            $valid = Str::contains($config['cloud-environments'][$environment]['domain'], '.') &&
                strtolower($config['cloud-environments'][$environment]['domain']) === $config['cloud-environments'][$environment]['domain'];

            if (!$valid) {
                $this->error("Invalid domain set for environment '$environment' in larasurf.json");

                return false;
            }

            $client = $this->getRoute53Client($config, $environment);

            $this->info('Updating Hosted Zone ID in larasurf.json');

            // todo: support more than 100 hosted zones
            $hosted_zones = $client->listHostedZones();

            $suffix = Str::afterLast($config['cloud-environments'][$environment]['domain'], '.');
            $domain_length = strlen($config['cloud-environments'][$environment]['domain']) - strlen($suffix) - 1;
            $domain = substr($config['cloud-environments'][$environment]['domain'], 0, $domain_length);

            if (Str::contains($domain, '.')) {
                $domain = Str::afterLast($domain, '.');
            }

            $domain .= '.' . $suffix;

            foreach ($hosted_zones['HostedZones'] as $hosted_zone) {
                if ($hosted_zone['Name'] === $domain . '.') {
                    $config['cloud-environments'][$environment]['aws-hosted-zone-id'] = str_replace('/hostedzone/', '', $hosted_zone['Id']);

                    return $this->writeLaraSurfConfig($config);
                }
            }

            $this->error("No hosted zone matching root domain '$domain' found.");

            return false;
        }

        return true;
    }

    protected function createStack($config, $environment)
    {
        $client = $this->getCloudFormationClient($config, $environment);

        $stack_name = $this->getCloudFormationStackName($config, $environment);

        $infrastructure_template_path = base_path('.cloudformation/infrastructure.yml');

        if (!File::exists($infrastructure_template_path)) {
            $this->error("File '.cloudformation/infrastructure.yml' does not exist");

            return false;
        }

        $template = File::get($infrastructure_template_path);

        $client->createStack([
            'Capabilities' => ['CAPABILITY_IAM'],
            'StackName' => $stack_name,
            'Parameters' => [
                [
                    'ParameterKey' => 'VpcName',
                    'ParameterValue' => "{$config['project-name']}-$environment",
                ],
            ],
            'Tags' => [
                [
                    'Key' => 'Project',
                    'Value' => $config['project-name'],
                ],
                [
                    'Key' => 'Environment',
                    'Value' => $environment,
                ],
            ],
            'TemplateBody' => $template,
        ]);

        $this->info('Stack creation initiated');

        $this->line("See https://console.aws.amazon.com/cloudformation/home?region={$config['cloud-environments'][$environment]['aws-region']} for more information");

        $finished = false;
        $success = false;
        $status = null;
        $tries = 0;
        $limit = 180;

        while (!$finished && $tries < $limit) {
            $result = $client->describeStacks([
                'StackName' => $stack_name,
            ]);

            if (isset($result['Stacks'][0]['StackStatus'])) {
                $status = $result['Stacks'][0]['StackStatus'];
                $finished = !str_ends_with($status, '_IN_PROGRESS');

                if ($finished) {
                    $success = $result['Stacks'][0]['StackStatus'] === 'CREATE_COMPLETE';
                } else {
                    $this->line('Stack creation is not yet finished, checking again in 10 seconds...');
                }
            } else {
                $this->warn('Unexpected response from AWS API, trying again in 10 seconds');
            }

            if (!$finished) {
                $this->sleepBar(10);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            $this->error('Stack failed to be created within 30 minutes');

            return false;
        } else if ($success) {
            $this->info('Stack created successfully');
        } else {
            $this->error("Stack creation failed with status: '$status'");
            $this->error("See https://console.aws.amazon.com/cloudformation/home?region={$config['cloud-environments'][$environment]['aws-region']} for more information");

            return false;
        }

        return true;
    }

    protected function afterCreateStackUpdateParameters($config, $environment)
    {
        $ssm_client = $this->getSsmClient($config, $environment);

        if (!$ssm_client) {
            return false;
        }

        $path = $this->getSsmParameterPath($config, $environment);

        $results = $ssm_client->getParametersByPath([
            'Path' => $path,
        ]);

        $app_key = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));

        $default_env_vars = [
            'APP_ENV' => $environment,
            'APP_KEY' => $app_key,
            'CACHE_DRIVER' => 'redis',
            'DB_CONNECTION' => 'mysql',
            'LOG_CHANNEL' => 'errorlog',
            'QUEUE_CONNECTION' => 'sqs',
            'MAIL_DRIVER' => 'ses',
        ];

        foreach ($default_env_vars as $key => $value) {
            $var_path = $this->getSsmParameterPath($config, $environment, $key);
            $exists = false;

            foreach ($results['Parameters'] as $parameter) {
                if ($parameter['Name'] === $var_path) {
                    $this->warn("Parameter $var_path already exists");
                    $exists = true;
                }
            }

            if (!$exists) {
                $ssm_client->putParameter([
                    'Name' => $var_path,
                    'Type' => 'SecureString',
                    'Value' => $value,
                    'Tags' => [
                        [
                            'Key' => 'Project',
                            'Value' => $config['project-name'],
                        ],
                        [
                            'Key' => 'Environment',
                            'Value' => $environment,
                        ],
                    ],
                ]);

                $this->info("Successfully set parameter '$var_path'");
            }

            $config['cloud-environments'][$environment]['variables'][] = $key;
        }

        $variables = array_values(array_unique($config['cloud-environments'][$environment]['variables']));

        sort($variables);

        $config['cloud-environments'][$environment]['variables'] = $variables;

        $success = $this->writeLaraSurfConfig($config);

        if (!$success) {
            return false;
        }

        return true;
    }

    protected function sleepBar($seconds)
    {
        $bar = $this->output->createProgressBar($seconds);

        $bar->start();

        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);
            $bar->advance();
        }

        $bar->finish();

        $this->newLine();
    }
}
