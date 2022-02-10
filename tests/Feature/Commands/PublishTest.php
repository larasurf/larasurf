<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class PublishTest extends TestCase
{
    public function testPublishCsFixerConfig()
    {
        if (File::exists(base_path('.php-cs-fixer.dist.php'))) {
            File::delete(base_path('.php-cs-fixer.dist.php'));
        }

        $this->artisan('larasurf:publish --cs-fixer')
            ->expectsOutput('Published code style fixer config successfully');

        $this->assertFileExists(base_path('.php-cs-fixer.dist.php'));
    }

    public function testPublishNginxLocalSslConfig()
    {
        if (!File::isDirectory(base_path('.docker/nginx/'))) {
            File::makeDirectory(base_path('.docker/nginx/'), 0755, true);
        }

        File::put(base_path('.docker/nginx/laravel.conf.template'), '');

        $this->artisan('larasurf:publish --nginx-local-tls')
            ->expectsOutput('Modified nginx config successfully');

        $this->assertTrue(Str::contains(File::get(base_path('.docker/nginx/laravel.conf.template')), 'listen 443 ssl;'));
    }

    public function testPublishEnvChanges()
    {
        if (!File::isDirectory(base_path('.docker/nginx/'))) {
            File::makeDirectory(base_path('.docker/nginx/'), 0755, true);
        }

        File::put(base_path('.docker/nginx/laravel.conf.template'), 'listen 443 ssl;');

        $contents = <<<EOF
APP_URL=http://localhost
CACHE_DRIVER=array
DB_CONNECTION=sqlite
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
UNMODIFIED=value
EOF;

        File::put(base_path('.env'), $contents);
        File::put(base_path('.env.example'), $contents);

        $this->artisan('larasurf:publish --env-changes')
            ->expectsOutput('Modified .env successfully')
            ->expectsOutput('Modified .env.example successfully');

        $env_file = array_map('trim', file(base_path('.env')));

        $this->assertTrue(in_array('APP_URL=https://localhost', $env_file));
        $this->assertTrue(in_array('CACHE_DRIVER=redis', $env_file));
        $this->assertTrue(in_array('DB_CONNECTION=mysql', $env_file));
        $this->assertTrue(in_array('QUEUE_CONNECTION=sqs', $env_file));
        $this->assertTrue(in_array('SESSION_DRIVER=redis', $env_file));
        $this->assertTrue(in_array('UNMODIFIED=value', $env_file));

        $env_example_file = array_map('trim', file(base_path('.env.example')));

        $this->assertTrue(in_array('APP_URL=https://localhost', $env_example_file));
        $this->assertTrue(in_array('CACHE_DRIVER=redis', $env_example_file));
        $this->assertTrue(in_array('DB_CONNECTION=mysql', $env_example_file));
        $this->assertTrue(in_array('QUEUE_CONNECTION=sqs', $env_example_file));
        $this->assertTrue(in_array('SESSION_DRIVER=redis', $env_file));
        $this->assertTrue(in_array('UNMODIFIED=value', $env_example_file));
    }

    public function testPublishCircleCIConfigLocal()
    {
        $this->createValidLaraSurfConfig('local');

        if (File::exists(base_path('.circleci/config.yml'))) {
            File::delete(base_path('.circleci/config.yml'));
        }

        if (File::exists(base_path('.circleci/docker-compose.ci.yml'))) {
            File::delete(base_path('.circleci/docker-compose.ci.yml'));
        }

        if (File::exists(base_path('.circleci/Dockerfile'))) {
            File::delete(base_path('.circleci/Dockerfile'));
        }

        if (File::exists(base_path('.circleci/inject-secrets.sh'))) {
            File::delete(base_path('.circleci/inject-secrets.sh'));
        }

        $this->artisan('larasurf:publish --circleci')
            ->expectsOutput('Published CircleCI configuration file successfully')
            ->expectsOutput('Published docker-compose file successfully')
            ->expectsOutput('Published Dockerfile file successfully');

        $this->assertFileExists(base_path('.circleci/config.yml'));
        $this->assertFileExists(base_path('.circleci/docker-compose.ci.yml'));
        $this->assertFileExists(base_path('.circleci/Dockerfile'));
    }

    public function testPublishCircleCIConfigLocalProduction()
    {
        $this->createValidLaraSurfConfig('local-production');

        if (File::exists(base_path('.circleci/config.yml'))) {
            File::delete(base_path('.circleci/config.yml'));
        }

        if (File::exists(base_path('.circleci/docker-compose.ci.yml'))) {
            File::delete(base_path('.circleci/docker-compose.ci.yml'));
        }

        if (File::exists(base_path('.circleci/Dockerfile'))) {
            File::delete(base_path('.circleci/Dockerfile'));
        }

        if (File::exists(base_path('.circleci/inject-secrets.sh'))) {
            File::delete(base_path('.circleci/inject-secrets.sh'));
        }

        $this->artisan('larasurf:publish --circleci')
            ->expectsOutput('Published CircleCI configuration file successfully')
            ->expectsOutput('Published docker-compose file successfully')
            ->expectsOutput('Published Dockerfile file successfully')
            ->expectsOutput('Published CircleCI inject secrets script successfully');

        $this->assertFileExists(base_path('.circleci/config.yml'));
        $this->assertFileExists(base_path('.circleci/docker-compose.ci.yml'));
        $this->assertFileExists(base_path('.circleci/Dockerfile'));
        $this->assertFileExists(base_path('.circleci/inject-secrets.sh'));
    }

    public function testPublishCircleCIConfigLocalStageProduction()
    {
        $this->createValidLaraSurfConfig('local-stage-production');

        if (File::exists(base_path('.circleci/config.yml'))) {
            File::delete(base_path('.circleci/config.yml'));
        }

        if (File::exists(base_path('.circleci/docker-compose.ci.yml'))) {
            File::delete(base_path('.circleci/docker-compose.ci.yml'));
        }

        if (File::exists(base_path('.circleci/Dockerfile'))) {
            File::delete(base_path('.circleci/Dockerfile'));
        }

        if (File::exists(base_path('.circleci/inject-secrets.sh'))) {
            File::delete(base_path('.circleci/inject-secrets.sh'));
        }

        $this->artisan('larasurf:publish --circleci')
            ->expectsOutput('Published CircleCI configuration file successfully')
            ->expectsOutput('Published docker-compose file successfully')
            ->expectsOutput('Published Dockerfile file successfully')
            ->expectsOutput('Published CircleCI inject secrets script successfully');

        $this->assertFileExists(base_path('.circleci/config.yml'));
        $this->assertFileExists(base_path('.circleci/docker-compose.ci.yml'));
        $this->assertFileExists(base_path('.circleci/Dockerfile'));
        $this->assertFileExists(base_path('.circleci/inject-secrets.sh'));
    }

    public function testPublishCloudFormation()
    {
        if (File::exists(base_path('.cloudformation/infrastructure.yml'))) {
            File::delete(base_path('.cloudformation/infrastructure.yml'));
        }

        $this->artisan('larasurf:publish --cloudformation')
            ->expectsOutput('Published infrastructure CloudFormation template successfully');

        $this->assertFileExists(base_path('.cloudformation/infrastructure.yml'));
    }

    public function testPublishGitIgnore()
    {
        if (File::exists(base_path('.gitignore'))) {
            File::delete(base_path('.gitignore'));
        }

        $this->artisan('larasurf:publish --gitignore')
            ->expectsOutput('Published .gitignore successfully');

        $this->assertFileExists(base_path('.gitignore'));
    }

    public function testPublishHealthCheck()
    {
        if (!File::isDirectory(base_path('routes'))) {
            File::makeDirectory(base_path('routes'));
        }

        if (!File::isDirectory(base_path('tests/Feature'))) {
            File::makeDirectory(base_path('tests/Feature'), 0755, true);
        }

        if (File::exists(base_path('routes/api.php'))) {
            File::delete(base_path('routes/api.php'));
        }

        if (File::exists(base_path('tests/Feature/HealthCheckTest.php'))) {
            File::delete(base_path('tests/Feature/HealthCheckTest.php'));
        }

        $this->artisan('larasurf:publish --healthcheck')
            ->expectsOutput('Published health check route successfully')
            ->expectsOutput('Published health check feature test successfully');

        $this->assertFileExists(base_path('routes/api.php'));
        $this->assertFileExists(base_path('tests/Feature/HealthCheckTest.php'));
    }

    public function testPublishProxies()
    {
        if (!File::isDirectory(app_path('Http/Middleware'))) {
            File::makeDirectory(app_path('Http/Middleware'));
        }

        File::put(app_path('Http/Middleware/TrustProxies.php'), 'protected $proxies;');

        $this->artisan('larasurf:publish --proxies')
            ->expectsOutput('Published trusted proxy changes successfully');

        $this->assertTrue(Str::contains(File::get(app_path('Http/Middleware/TrustProxies.php')), "protected \$proxies = '*';"));
    }
}
