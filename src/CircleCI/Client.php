<?php

namespace LaraSurf\LaraSurf\CircleCI;

use Illuminate\Support\Facades\Http;
use LaraSurf\LaraSurf\Exceptions\CircleCI\ConfigurationNotYetSetException;
use LaraSurf\LaraSurf\Exceptions\CircleCI\RequestFailedException;

class Client
{
    protected string $base_url = 'https://circleci.com/api/v2';
    protected string $api_key;
    protected string $project;

    public function configure(string $api_key, string $project): static
    {
        $this->api_key = $api_key;
        $this->project = $project;

        return $this;
    }

    public function listEnvironmentVariables(): array
    {
        $response = Http::withHeaders($this->headers())->get("{$this->base_url}/project/" . $this->projectSlug() . '/envvar');

        if ($response->failed()) {
            throw new RequestFailedException($response);
        }

        return collect($response->json('items'))->keyBy('name')->map(fn ($item) => $item['value'])->toArray();
    }

    public function createEnvironmentVariable(string $name, string $value): bool
    {
        $response = Http
            ::withHeaders($this->headers())
            ->post("{$this->base_url}/project/" . $this->projectSlug() . '/envvar', compact('name', 'value'));

        if ($response->failed()) {
            throw new RequestFailedException($response);
        }

        return true;
    }

    public function deleteEnvironmentVariable(string $name): bool
    {
        $response = Http
            ::withHeaders($this->headers())
            ->delete("{$this->base_url}/project/" . $this->projectSlug() . "/envvar/$name");

        if ($response->failed()) {
            throw new RequestFailedException($response);
        }

        return true;
    }

    public function createUserKey(): bool
    {
        $response = Http
            ::withHeaders($this->headers())
            ->post("{$this->base_url}/project/" . $this->projectSlug() . '/checkout-key', [
                'type' => 'user-key',
            ]);

        if ($response->failed()) {
            throw new RequestFailedException($response);
        }

        return true;
    }

    public function checkApiKey(): bool
    {
        return !Http::withHeaders($this->headers())->get("{$this->base_url}/me")->failed();
    }

    public function projectExists(): bool
    {
        return !Http::withHeaders($this->headers())->get("{$this->base_url}/project/" . $this->projectSlug())->failed();
    }

    protected function headers(): array
    {
        if (!$this->api_key) {
            throw new ConfigurationNotYetSetException();
        }

        return [
            'Circle-Token' => $this->api_key,
        ];
    }

    protected function projectSlug(): string
    {
        if (!$this->project) {
            throw new ConfigurationNotYetSetException();
        }

        return "gh/{$this->project}";
    }
}
