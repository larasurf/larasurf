<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class CircleCITest extends TestCase
{
    public function testSetApiKey()
    {
        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $api_key = Str::random();

        Http::fake([
            'https://circleci.com/api/v2/me' => Http::response('', 200),
        ]);

        $this->artisan('larasurf:circleci set-api-key')
            ->expectsQuestion('Enter your CircleCI API token:', $api_key)
            ->expectsOutput('Verifying API token...')
            ->expectsOutput('Verified API key successfully')
            ->expectsOutput('Updated file \'.circleci/api-key.txt\' successfully')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(base_path('.circleci/api-key.txt')));
        $this->assertEquals($api_key, File::get(base_path('.circleci/api-key.txt')));
    }

    public function testSetApiKeyInvalid()
    {
        $this->createGitConfig($this->faker->word . '/' . $this->faker->word);

        $api_key = Str::random();

        Http::fake([
            'https://circleci.com/api/v2/me' => Http::response('', 404),
        ]);

        $this->artisan('larasurf:circleci set-api-key')
            ->expectsQuestion('Enter your CircleCI API token:', $api_key)
            ->expectsOutput('Verifying API token...')
            ->expectsOutput('Failed to verify API key')
            ->assertExitCode(1);

        $this->assertFalse(File::exists('.circleci/api-key.txt'));
    }

    public function testClearApiKey()
    {
        $this->createCircleCIApiKey(Str::random());

        $this->artisan('larasurf:circleci clear-api-key')
            ->expectsOutput('Deleted file \'.circleci/api-key.txt\' successfully')
            ->assertExitCode(0);
    }

    public function testClearApiKeyNotFound()
    {
        $this->artisan('larasurf:circleci clear-api-key')
            ->expectsOutput('No file exists at: .circleci/api-key.txt')
            ->assertExitCode(1);
    }
}
