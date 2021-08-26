<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Aws\AwsClient;
use Illuminate\Support\Facades\File;
use LaraSurf\LaraSurf\Constants\Cloud;
use League\Flysystem\FileNotFoundException;
use Symfony\Component\Console\Output\ConsoleOutput;

class CloudFormationClient extends Client
{
    public function createStack(
        int $db_storage_size,
        string $db_instance_class,
        string $db_username,
        string $db_password,
        string $db_admin_prefix_list_id,
        ConsoleOutput $output = null,
        string $wait_message = ''
    )
    {
        $this->validateEnvironmentIsSet();

        $stack_name = $this->stackName();

        $this->client->createStack([
            'Capabilities' => ['CAPABILITY_IAM'],
            'StackName' => $stack_name,
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
                [
                    'ParameterKey' => 'DBAdminAccessPrefixListId',
                    'ParameterValue' => $db_admin_prefix_list_id,
                ],
            ],
            'Tags' => $this->resourceTags('cloudformation-stack'),
            'TemplateBody' => $this->template(),
        ]);

        $client = $this->client;

        $this->waitForFinish(120, 30, function (&$success) use ($stack_name, $client) {
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
    }

    public function deleteStack()
    {
        $this->client->deleteStack([
            'StackName' => $this->stackName(),
        ]);
    }

    protected function makeClient(array $args): AwsClient
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
