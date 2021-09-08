<?php

namespace LaraSurf\LaraSurf\AwsClients;

use Illuminate\Support\Str;

class SsmClient extends Client
{
    public function parameterExists(string $name): bool
    {
        $path = $this->parameterPath($name);
        $parameters = $this->listParameters();

        return in_array($path, $parameters);
    }

    public function getParameter(string $name): string|false
    {
        $result = $this->client->getParameter([
            'Name' => $this->parameterPath($name),
            'WithDecryption' => true,
        ]);

        if (empty($result['Parameter']['Value'])) {
            return false;
        }

        return $result['Parameter']['Value'];
    }

    public function putParameter(string $name, string $value)
    {
        $parameter_path = $this->parameterPath($name);

        $existing_parameters = $this->listParameters();

        $args = [
            'Name' => $parameter_path,
            'Type' => 'SecureString',
            'Value' => $value,
        ];

        if (in_array($parameter_path, $existing_parameters)) {
            $args['Overwrite'] = true;
        } else {
            $args['Tags'] = $this->resourceTags();
        }

        $this->client->putParameter($args);
    }

    public function deleteParameter(string $path)
    {
        $this->client->deleteParameter([
            'Name' => $path,
        ]);
    }

    public function listParameters(bool $decrypt = false): array
    {
        $parameters = [];
        $next_token = null;

        do {
            $results = $this->client->getParametersByPath([
                'Path' => $this->parameterPath(),
                'NextToken' => $next_token,
                'WithDecryption' => $decrypt,
            ]);

            if ($decrypt) {
                foreach ($results['Parameters'] as $parameter) {
                    $parameters[$parameter['Name']] = $parameter['Value'];
                }
            } else {
                $parameters = array_merge(
                    $parameters,
                    array_column($results['Parameters'], 'Name')
                );
            }

            $next_token = $results['NextToken'] ?? false;
            $done = !$next_token || !($results['Parameters'] ?? false);

            if (!$done) {
                sleep(1);
            }
        } while (!$done);

        return $parameters;
    }

    public function listParameterArns(bool $assoc = true): array
    {
        $parameters = [];
        $next_token = null;

        do {
            $results = $this->client->getParametersByPath([
                'Path' => $this->parameterPath(),
                'NextToken' => $next_token,
            ]);

            if ($assoc) {
                foreach ($results['Parameters'] as $parameter) {
                    $parameters[Str::afterLast($parameter['Name'], '/')] = $parameter['ARN'];
                }
            } else {
                $parameters = array_merge(
                    $parameters,
                    array_column($results['Parameters'], 'ARN')
                );
            }

            $next_token = $results['NextToken'] ?? false;
            $done = !$next_token || !($results['Parameters'] ?? false);

            if (!$done) {
                sleep(1);
            }
        } while (!$done);

        return $parameters;
    }

    protected function makeClient(array $args): \Aws\Ssm\SsmClient
    {
        return new \Aws\Ssm\SsmClient($args);
    }

    protected function parameterPath(string $parameter = null): string
    {
        $this->validateEnvironmentIsSet();

        $parameter = $parameter ?? '';

        return '/' . $this->project_name . '-' . $this->project_id . '/' . $this->environment . '/' . $parameter;
    }
}
