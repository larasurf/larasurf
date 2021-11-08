<?php

namespace LaraSurf\LaraSurf\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\CircleCI\Client;
use LaraSurf\LaraSurf\Tests\TestCase;

class CircleCIClientTest extends TestCase
{
    protected Client $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = new Client(Str::random(), $this->faker->word);
    }

    public function testListEnvironmentVariables()
    {
        $name1 = Str::random();
        $name2 = Str::random();

        Http::fake(fn ($request) => Http::response([
            'items' => [
                [
                    'name' => $name1,
                    'value' => Str::random(),
                ],
                [
                    'name' => $name2,
                    'value' => Str::random(),
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/json',
        ]));

        $vars = $this->client->listEnvironmentVariables();

        $this->assertArrayHasKey($name1, $vars);
        $this->assertArrayHasKey($name2, $vars);
    }

    public function testCreateEnvironmentVariable()
    {
        Http::fake(fn ($request) => Http::response([], 201, [
            'Content-Type' => 'application/json',
        ]));

        $response = $this->client->createEnvironmentVariable($this->faker->word, Str::random());

        $this->assertTrue($response);
    }

    public function testDeleteEnvironmentVariable()
    {
        Http::fake(fn ($request) => Http::response([], 200, [
            'Content-Type' => 'application/json',
        ]));

        $response = $this->client->deleteEnvironmentVariable($this->faker->word);

        $this->assertTrue($response);
    }

    public function testCreateUserKey()
    {
        Http::fake(fn ($request) => Http::response([], 201, [
            'Content-Type' => 'application/json',
        ]));

        $response = $this->client->createUserKey();

        $this->assertTrue($response);
    }

    public function testCheckApiKey()
    {
        Http::fake(fn ($request) => Http::response([], 200, [
            'Content-Type' => 'application/json',
        ]));

        $response = $this->client->checkApiKey();

        $this->assertTrue($response);
    }

    public function testCheckApiKeyInvalid()
    {
        Http::fake(fn ($request) => Http::response([], 403, [
            'Content-Type' => 'application/json',
        ]));

        $response = $this->client->checkApiKey();

        $this->assertFalse($response);
    }

    public function testProjectExists()
    {
        Http::fake(fn ($request) => Http::response([], 200, [
            'Content-Type' => 'application/json',
        ]));

        $response = $this->client->projectExists();

        $this->assertTrue($response);
    }

    public function testProjectDoesntExist()
    {
        Http::fake(fn ($request) => Http::response([], 404, [
            'Content-Type' => 'application/json',
        ]));

        $response = $this->client->projectExists();

        $this->assertFalse($response);
    }
}
