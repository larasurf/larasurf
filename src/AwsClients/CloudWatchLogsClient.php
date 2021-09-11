<?php

namespace LaraSurf\LaraSurf\AwsClients;

class CloudWatchLogsClient extends Client
{
    public function listLogStream(string $group_name, string $stream_name): array|false
    {
        $result = $this->client->getLogEvents([
            'logGroupName' => $group_name,
            'logStreamName' => $stream_name,
            'startFromHead' => true,
        ]);

        return array_column($result['events'], 'message');
    }

    protected function makeClient(array $args): \Aws\CloudWatchLogs\CloudWatchLogsClient
    {
        return new \Aws\CloudWatchLogs\CloudWatchLogsClient($args);
    }
}
