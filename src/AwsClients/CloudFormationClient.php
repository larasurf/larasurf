<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\Exception\AwsException;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\Exceptions\AwsClients\TimeoutExceededException;
use League\Flysystem\FileNotFoundException;
use Symfony\Component\Console\Cursor;

class CloudFormationClient extends Client
{
    const STACK_STATUS_CREATE_COMPLETE = 'CREATE_COMPLETE';
    const STACK_STATUS_UPDATE_COMPLETE = 'UPDATE_COMPLETE';

    public function createStack(
        string $domain,
        string $certificate_arn,
        int $db_storage_size,
        string $db_instance_class,
        string $db_username,
        string $db_password
    )
    {
        $this->validateEnvironmentIsSet();

        $this->client->createStack([
            'Capabilities' => ['CAPABILITY_IAM'],
            'StackName' => $this->stackName(),
            'Parameters' => [
                [
                    'ParameterKey' => 'ProjectName',
                    'ParameterValue' => $this->project_name,
                ],
                [
                    'ParameterKey' => 'ProjectId',
                    'ParameterValue' => $this->project_id,
                ],
                [
                    'ParameterKey' => 'EnvironmentName',
                    'ParameterValue' => $this->environment,
                ],
                [
                    'ParameterKey' => 'DomainName',
                    'ParameterValue' => $domain,
                ],
                [
                    'ParameterKey' => 'CertificateArn',
                    'ParameterValue' => $certificate_arn,
                ],
                [
                    'ParameterKey' => 'DBStorageSize',
                    'ParameterValue' => (string) $db_storage_size,
                ],
                [
                    'ParameterKey' => 'DBInstanceClass',
                    'ParameterValue' => $db_instance_class,
                ],
                [
                    'ParameterKey' => 'DBAvailabilityZone',
                    'ParameterValue' => $this->environment === Cloud::ENVIRONMENT_PRODUCTION
                        ? ''
                        : "{$this->aws_region}a",
                ],
                [
                    'ParameterKey' => 'DBVersion',
                    'ParameterValue' => '8.0.25',
                ],
                [
                    'ParameterKey' => 'DBMasterUsername',
                    'ParameterValue' => $db_username,
                ],
                [
                    'ParameterKey' => 'DBMasterPassword',
                    'ParameterValue' => $db_password,
                ],
            ],
            'Tags' => $this->resourceTags(),
            'TemplateBody' => $this->template(),
        ]);
    }

    public function updateStack(
        ?string $domain,
        ?string $certificate_arn,
        ?int $db_storage_size,
        ?string $db_instance_class
    )
    {
        $update_params = [];

        foreach ([
                     'DomainName' => $domain,
                     'CertificateArn' => $certificate_arn,
                     'DBStorageSize' => $db_storage_size,
                     'DBInstanceClass' => $db_instance_class,
                 ] as $key => $value) {
            if ($value) {
                $update_params[] = [
                    'ParameterKey' => $key,
                    'ParameterValue' => $value,
                ];
            } else {
                $update_params[] = [
                    'ParameterKey' => $key,
                    'UsePreviousValue' => true,
                ];
            }
        }

        $this->client->updateStack([
            'Capabilities' => ['CAPABILITY_IAM'],
            'StackName' => $this->stackName(),
            'Parameters' => [
                [
                    'ParameterKey' => 'ProjectName',
                    'UsePreviousValue' => true,
                ],
                [
                    'ParameterKey' => 'ProjectId',
                    'UsePreviousValue' => true,
                ],
                [
                    'ParameterKey' => 'EnvironmentName',
                    'UsePreviousValue' => true,
                ],
                [
                    'ParameterKey' => 'DBAvailabilityZone',
                    'UsePreviousValue' => true,
                ],
                [
                    'ParameterKey' => 'DBVersion',
                    'UsePreviousValue' => true,
                ],
                [
                    'ParameterKey' => 'DBMasterUsername',
                    'UsePreviousValue' => true,
                ],
                [
                    'ParameterKey' => 'DBMasterPassword',
                    'UsePreviousValue' => true,
                ],
                ...$update_params,
            ],
            'TemplateBody' => $this->template(),
        ]);
    }

    public function deleteStack()
    {
        $this->client->deleteStack([
            'StackName' => $this->stackName(),
        ]);
    }

    public function stackStatus(): string|false
    {
        try {
            $result = $this->client->describeStacks([
                'StackName' => $this->stackName(),
            ]);
        } catch (AwsException $e) {
            //
        }


        if (empty($result['Stacks'][0])) {
            return false;
        }

        return $result['Stacks'][0]['StackStatus'] ?? false;
    }

    public function stackOutput(array|string $keys): array|string|false
    {
        $array_keys = (array) $keys;

        $result = $this->client->describeStacks([
            'StackName' => $this->stackName(),
        ]);

        $keyed_values = [];

        if (isset($result['Stacks'][0]['Outputs'])) {
            foreach ($result['Stacks'][0]['Outputs'] as $output) {
                if (in_array($output['OutputKey'], $array_keys)) {
                    $keyed_values[$output['OutputKey']] = $output['OutputValue'];
                }
            }
        }

        return is_array($keys) ? $keyed_values : ($keyed_values[$keys] ?? false);
    }

    public function waitForStackInfoPanel(string $success_status, OutputStyle $output = null, $word = 'created'): array
    {
        $finished = false;
        $tries = 0;
        $success = false;
        $limit = 60;
        $wait_seconds = 60;
        $status = null;

        while (!$finished && $tries < $limit) {
            try {
                $result = $this->client->describeStacks([
                    'StackName' => $this->stackName(),
                ]);

                if (isset($result['Stacks'][0]['StackStatus'])) {
                    $status = $result['Stacks'][0]['StackStatus'];
                    $finished = !str_ends_with($status, '_IN_PROGRESS');

                    if ($finished) {
                        $success = $status === $success_status;
                    }
                }
            } catch (AwsException $e) {
                $finished = true;
                $status = 'DELETED';
                $success = $success_status === $status;
            }

            if (!$finished && $output) {
                for ($i = 1; $i <= $wait_seconds; $i++) {
                    $cursor = new Cursor($output);
                    $cursor->clearScreen();
                    $bars = str_repeat('=', $i);
                    $empty = str_repeat('-', $wait_seconds - $i);

                    $seconds = $wait_seconds - $i;
                    $padding = $seconds < 10 ? ' ' : '';

                    $word_padding = str_repeat(' ', strlen($word) - 7);

                    $message =
                        "╔══════════════════════════════════════════════════════════════════════════════╗" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "║                 <info>Your CloudFormation stack is being $word!</info>$word_padding                  ║" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "║           <info>You can view the progress of your stack operation here:</info>            ║" . PHP_EOL .
                        "║      https://console.aws.amazon.com/cloudformation/home?region={$this->aws_region}     ║" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "╠══════════════════════════════════════════════════════════════════════════════╣" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "║                 Checking for status updates in $seconds seconds...$padding                 ║" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "║        [<info>$bars</info>$empty]        ║" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "╠══════════════════════════════════════════════════════════════════════════════╣" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "║         <info>This would also be a great time to review the documentation!</info>         ║" . PHP_EOL .
                        "║                         https://larasurf.com/docs                            ║" . PHP_EOL .
                        "║                                                                              ║" . PHP_EOL .
                        "╚══════════════════════════════════════════════════════════════════════════════╝" . PHP_EOL
                        . PHP_EOL .
                        "If you do not wish to wait, you can safely exit this screen with Ctrl+C" . PHP_EOL;

                    $output->writeln($message);

                    sleep(1);
                }
            } else if (!$finished) {
                sleep($wait_seconds);
            }

            $tries++;
        }

        if ($tries >= $limit) {
            throw new TimeoutExceededException($tries * $limit);
        }

        if ($output) {
            $cursor = new Cursor($output);
            $cursor->clearScreen();
        }

        return [
            'success' => $success,
            'status' => $status,
        ];
    }

    public static function templatePath(): string
    {
        return base_path('.cloudformation/infrastructure.yml');
    }

    protected function makeClient(array $args): \Aws\CloudFormation\CloudFormationClient
    {
        return new \Aws\CloudFormation\CloudFormationClient($args);
    }

    protected function stackName()
    {
        $this->validateEnvironmentIsSet();

        return $this->project_name . '-' . $this->project_id . '-' . $this->environment;
    }

    protected function template(): string
    {
        $path = static::templatePath();

        if (!File::exists($path)) {
            throw new FileNotFoundException($path);
        }

        return File::get($path);
    }
}
