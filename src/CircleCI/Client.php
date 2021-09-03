<?php

namespace LaraSurf\LaraSurf\CircleCI;

use Illuminate\Support\Facades\Http;
use LaraSurf\LaraSurf\Exceptions\CircleCI\RequestFailedException;

class Client
{
    protected array $headers;
    protected string $base_url = 'https://circleci.com/api/v2';

    public function __construct(string $api_key, protected string $project)
    {
        $this->headers = [
            'Circle-Token' => $api_key
        ];
    }

    public function listEnvironmentVariables(): array
    {
        $response = Http::withHeaders($this->headers)->get("{$this->base_url}/" . $this->projectSlug() . '/envvar');

        if ($response->failed()) {
            throw new RequestFailedException($response);
        }

        return collect($response->json('items'))->keyBy('name')->map(fn ($item) => $item['value'])->toArray();
    }

    public function createEnvironmentVariable(string $name, string $value): bool
    {
        $response = Http
            ::withHeaders($this->headers)
            ->post("{$this->base_url}/" . $this->projectSlug() . '/envvar', compact('name', 'value'));

        if ($response->failed()) {
            throw new RequestFailedException($response);
        }
        
        return true;
    }

    public function deleteEnvironmentVariable(string $name): bool
    {
        $response = Http
            ::withHeaders($this->headers)
            ->delete("{$this->base_url}/" . $this->projectSlug() . "/envvar/$name");

        if ($response->failed()) {
            throw new RequestFailedException($response);
        }

        return true;
    }

    public function checkApiKey(): bool
    {
        return !Http::withHeaders($this->headers)->get('/me')->failed();
    }

    protected function projectSlug(): string
    {
        return "gh/{$this->project}";
    }
}
