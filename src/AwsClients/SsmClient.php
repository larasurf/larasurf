<?php

namespace LaraSurf\LaraSurf\AwsClients;

class SsmClient extends Client
{
    public function parameterExists($name): bool
    {
        $path = $this->parameterPath($name);
        $parameters = $this->listParameters();

        return in_array($path, $parameters);
    }

    public function getParameter($name): string|false
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

    public function putParameter($name, $value)
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

    public function deleteParameter($name)
    {
        $path = $this->parameterPath($name);

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
                    $parameter[$parameter['Name']] = $parameter['Value'];
                }
            } else {
                $parameters = array_merge(
                    $parameters,
                    array_column($results['Parameters'], 'Name')
                );
            }

            $next_token = $results['NextToken'] ?? false;
            $done = !$next_token || !($results['Parameters'] ?? false);
        } while (!$done);

        return $parameters;
    }

    protected function makeClient(array $args): \Aws\Ssm\SsmClient
    {
        return new \Aws\Ssm\SsmClient($args);
    }

    protected function parameterPath($parameter = null): string
    {
        $this->validateEnvironmentIsSet();

        $parameter = $parameter ?? '';

        return '/' . $this->project_name . '-' . $this->project_id . '/' . $this->environment . '/' . $parameter;
    }
}
