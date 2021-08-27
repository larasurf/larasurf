<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\Constants\Cloud;
use League\Flysystem\FileNotFoundException;

class CloudFormationClient extends Client
{
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
                    'ParameterValue' => $this->project_name . '-' . $this->project_id,
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
    
    public function waitForStackUpdate(OutputStyle $output = null, string $wait_message = ''): array
    {
        $stack_name = $this->stackName();

        $client = $this->client;

        $status = null;

        $success = $this->waitForFinish(120, 30, function (&$success) use ($stack_name, $client, &$status) {
            $result = $client->describeStacks([
                'StackName' => $stack_name,
            ]);

            if (isset($result['Stacks'][0]['StackStatus'])) {
                $status = $result['Stacks'][0]['StackStatus'];
                $finished = !str_ends_with($status, '_IN_PROGRESS');

                if ($finished) {
                    $success = $status === 'CREATE_COMPLETE';

                    return true;
                }
            }

            return false;
        }, $output, $wait_message);

        return [
            'success' => $success,
            'status' => $status,
        ];
    }

    public function deleteStack()
    {
        $this->client->deleteStack([
            'StackName' => $this->stackName(),
        ]);
    }

    public function stackStatus(): string|false
    {
        $result = $this->client->describeStacks([
            'StackName' => $this->stackName(),
        ]);

        if (empty($result['Stacks'][0])) {
            return false;
        }

        return $result['Stacks'][0]['StackStatus'];
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

    protected static function templatePath(): string
    {
        return base_path('.cloudformation/infrastructure.yml');
    }
}
