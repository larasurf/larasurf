<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\Credentials\Credentials;
use Aws\Exception\CredentialsException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\Exceptions\AwsClients\EnvironmentNotSetException;
use LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException;
use Symfony\Component\Console\Helper\ProgressBar;

abstract class Client
{
    abstract protected function makeClient(array $args);

    protected $client;
    protected string $project_name;
    protected string $project_id;
    protected string $aws_profile;
    protected string $aws_region;
    protected ?string $environment;

    public function configure(string $project_name, string $project_id, string $aws_profile, string $aws_region, ?string $environment = null): static
    {
        $this->project_name = $project_name;
        $this->project_id = $project_id;
        $this->aws_profile = $aws_profile;
        $this->aws_region = $aws_region;
        $this->environment = $environment;

        $this->client = $this->makeClient($this->clientArguments());

        return $this;
    }

    protected static function credentialsProvider($profile): callable
    {
        return function() use ($profile) {
            $credentials_file_path = static::awsCredentialsFilePath();

            if (!File::exists($credentials_file_path)) {
                return new RejectedPromise(new CredentialsException("File does not exist: $credentials_file_path"));
            }

            $credentials = parse_ini_file($credentials_file_path, true);

            if (!isset($credentials[$profile])) {
                return new RejectedPromise(new CredentialsException("Profile '$profile' does not exist in $credentials_file_path"));
            }

            if (empty($credentials[$profile]['aws_access_key_id'])) {
                return new RejectedPromise(new CredentialsException("Profile '$profile' does not contain 'aws_access_key_id'"));
            }

            if (empty($credentials[$profile]['aws_secret_access_key'])) {
                return new RejectedPromise(new CredentialsException("Profile '$profile' does not contain 'aws_secret_access_key'"));
            }

            return Create::promiseFor(
                new Credentials($credentials[$profile]['aws_access_key_id'], $credentials[$profile]['aws_secret_access_key'])
            );
        };
    }

    protected static function awsCredentialsFilePath(): string
    {
        return '/larasurf/aws/credentials';
    }

    protected function clientArguments(): array
    {
        $credentials = static::credentialsProvider($this->aws_profile);

        return [
            'version' => 'latest',
            'region' => $this->aws_region,
            'credentials' => $credentials,
        ];
    }

    protected function resourceTags(): array
    {
        return [
            [
                'Key' => 'Project',
                'Value' => $this->project_name . '-' . $this->project_id,
            ],
            [
                'Key' => 'Environment',
                'Value' => $this->environment,
            ],
        ];
    }

    protected function waitForFinish(int $limit, int $wait_seconds, callable $operation, \Illuminate\Console\OutputStyle $output = null, string $wait_message = ''): bool
    {
        $finished = false;
        $tries = 0;
        $success = false;

        while (!$finished && $tries < $limit) {
            $finished = $operation($success);

            if (!$finished && $output) {
                $output->writeln($wait_message);

                $bar = new ProgressBar($output, $wait_seconds);
                $bar->setBarCharacter('=');
                $bar->setProgressCharacter('=');

                $bar->start();

                for ($i = 0; $i < $wait_seconds; $i++) {
                    sleep(1);
                    $bar->advance();
                }

                $bar->finish();

                $output->write(PHP_EOL);
            } else if (!$finished) {
                sleep($wait_seconds);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            throw new TimeoutExceededException($tries * $limit);
        }

        return $success;
    }
}
