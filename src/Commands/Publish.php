<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Commands\Traits\DerivesAppUrl;
use LaraSurf\LaraSurf\Commands\Traits\InteractsWithLaraSurfConfig;

class Publish extends Command
{
    use DerivesAppUrl;
    use InteractsWithLaraSurfConfig;

    /**
     * @var string
     */
    protected $signature = 'larasurf:publish {--cs-fixer} {--nginx-local-ssl} {--env-changes} {--circleci} {--cloudformation} {--gitignore} {--healthcheck} {--proxies}';

    /**
     * @var string
     */
    protected $description = 'Publish or make changes to various files as part of LaraSurf\'s post-install process';

    /**
     * Handle the command.
     */
    public function handle()
    {
        foreach ([
                     'cs-fixer' => [$this, 'publishCsFixerConfig'],
                     'nginx-local-ssl' => [$this, 'publishNginxLocalSslConfig'],
                     'env-changes' => [$this, 'publishEnvChanges'],
                     'circleci' => [$this, 'publishCircleCIConfig'],
                     'cloudformation' => [$this, 'publishCloudFormation'],
                     'gitignore' => [$this, 'publishGitIgnore'],
                     'healthcheck' => [$this, 'publishHealthCheck'],
                     'proxies' => [$this, 'publishProxies']
                 ] as $option => $method) {
            if ($this->option($option)) {
                $method();
            }
        }
    }

    /**
     * Publish the code style fixer configuration file.
     */
    protected function publishCsFixerConfig()
    {
        $success = File::copy(__DIR__ . '/../../templates/.php-cs-fixer.dist.php', base_path('.php-cs-fixer.dist.php'));

        if ($success) {
            $this->info('Published code style fixer config successfully');
        } else {
            $this->error('Failed to publish code style fixer config');
        }
    }

    /**
     * Update the local NGINX configuration file to support SSL.
     */
    protected function publishNginxLocalSslConfig()
    {
        $nginx_config_path = base_path('.docker/nginx/laravel.conf.template');

        if (File::exists($nginx_config_path)) {
            $https_config = File::get(__DIR__ . '/../../templates/nginx-https.conf');
            $current_config = File::get($nginx_config_path);

            if (!Str::contains($current_config, 'listen 443 ssl;')) {
                $new_config = $current_config . PHP_EOL . PHP_EOL . $https_config;

                $success = File::put($nginx_config_path, $new_config);

                if ($success) {
                    $this->info('Modified nginx config successfully');
                } else {
                    $this->error('Failed to modify nginx config');
                }
            } else {
                $this->warn('NGINX is already configured to listen on port 443');
            }
        } else {
            $this->error('Failed to modify nginx config; file does not exist');
        }
    }

    /**
     * Update the local .env and .env.example files with configurations specific to LaraSurf.
     */
    protected function publishEnvChanges()
    {
        $url = self::deriveAppUrl();

        foreach (['.env', '.env.example'] as $file) {
            $env_file = base_path($file);

            if (File::exists($env_file)) {
                $contents = array_map('trim', file($env_file));

                if ($env_file === '.env') {
                    $app_url = $url;
                } else {
                    $app_url = $env_file === '.env.example' && Str::startsWith($url, 'https:')
                        ? 'https://localhost'
                        : 'http://localhost';
                }

                foreach ($contents as &$content) {
                    foreach ([
                                 'APP_URL=' => "$app_url",
                                 'CACHE_DRIVER=' => 'redis',
                                 'DB_CONNECTION=' => 'mysql',
                                 'QUEUE_CONNECTION=' => 'sqs',
                             ] as $find => $append) {
                        if (str_starts_with($content, $find)) {
                            $content = $find . $append;
                        }
                    }
                }

                $success = File::put($env_file, implode(PHP_EOL, array_merge($contents, [''])));

                if ($success) {
                    $this->info("Modified $file successfully");
                } else {
                    $this->error("Failed to modify $file");
                }
            }
        }
    }

    /**
     * Publish the relevant CircleCI configuration file depending on the configured environment.
     * Publish the bash script used for injecting secrets into the CloudFormation template within CircleCI.
     *
     * @throws \LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigKeyException
     */
    protected function publishCircleCIConfig()
    {
        $production = static::larasurfConfig()->exists('environments.production');
        $stage = static::larasurfConfig()->exists('environments.stage');

        if ($production && $stage) {
            $this->publishCircleCI('config.local-stage-production.yml');
        } else if ($production) {
            $this->publishCircleCI('config.local-production.yml');
        } else {
            $this->publishCircleCI('config.local.yml');
        }

        if ($production || $stage) {
            $this->publishCircleCIInjectSecretsScript();
        }
    }

    /**
     * Publish the bash script used for injecting secrets into the CloudFormation template within CircleCI.
     */
    protected function publishCircleCIInjectSecretsScript()
    {
        if (!File::isDirectory(base_path('.circleci'))) {
            File::makeDirectory(base_path('.circleci'));
        }

        $success = File::copy(__DIR__ . "/../../templates/circleci/inject-secrets.sh", '.circleci/inject-secrets.sh');

        if ($success) {
            $this->info('Published CircleCI inject secrets script successfully');
        } else {
            $this->error('Failed to publish CircleCI inject secrets script');
        }
    }

    /**
     * Publish a specific CircleCI configuration file.
     *
     * @param string $filename
     */
    protected function publishCircleCI(string $filename)
    {
        if (!File::isDirectory(base_path('.circleci'))) {
            File::makeDirectory(base_path('.circleci'));
        }

        $circle_config_path = base_path('.circleci/config.yml');

        if (File::exists($circle_config_path)) {
            $this->warn("File '.circleci/config.yml' exists");
        } else {
            $success = File::copy(__DIR__ . "/../../templates/circleci/$filename", $circle_config_path);

            if ($success) {
                $this->info('Published CircleCI configuration file successfully');
            } else {
                $this->error('Failed to publish CircleCI configuration file');
            }
        }

        $docker_compose_path = base_path('.circleci/docker-compose.ci.yml');

        if (File::exists($docker_compose_path)) {
            $this->warn("File '.circleci/docker-compose.ci.yml' exists");
        } else {
            $success = File::copy(__DIR__ . '/../../templates/circleci/docker-compose.ci.yml', $docker_compose_path);

            if ($success) {
                $this->info('Published docker-compose file successfully');
            } else {
                $this->error('Failed to publish docker-compose file');
            }
        }

        $dockerfile_path = base_path('.circleci/Dockerfile');

        if (File::exists($dockerfile_path)) {
            $this->warn("File '.circleci/Dockerfile' exists");
        } else {
            $success = File::copy(__DIR__ . '/../../templates/circleci/Dockerfile', $dockerfile_path);

            if ($success) {
                $this->info('Published Dockerfile file successfully');
            } else {
                $this->error('Failed to publish Dockerfile file');
            }
        }
    }

    /**
     * Publish the CloudFormation template.
     */
    protected function publishCloudFormation()
    {
        if (!File::isDirectory(base_path('.cloudformation'))) {
            File::makeDirectory(base_path('.cloudformation'));
        }

        $infrastructure_template_path = base_path('.cloudformation/infrastructure.yml');

        if (File::exists($infrastructure_template_path)) {
            $this->warn("File '.cloudformation/infrastructure.yml' exists");
        } else {
            $success = File::copy(__DIR__ . "/../../templates/cloudformation/infrastructure.yml", $infrastructure_template_path);

            if ($success) {
                $this->info('Published infrastructure CloudFormation template successfully');
            } else {
                $this->error('Failed to publish infrastructure CloudFormation template');
            }
        }
    }

    /**
     * Publish the updated .gitignore file.
     */
    protected function publishGitIgnore()
    {
        $contents = File::get(__DIR__ . '/../../templates/gitignore.txt');

        if (!File::put(base_path('.gitignore'), $contents)) {
            $this->error('Failed to publish .gitignore');
        } else {
            $this->info('Published .gitignore successfully');
        }
    }

    /**
     * Update the api routes file to contain a health check route.
     */
    protected function publishHealthCheck()
    {
        $path = base_path('routes/api.php');

        if (!File::exists($path)) {
            $this->error("Failed to find file at path: $path");
        }

        $append = <<<EOF

// AWS Health Check Route
Route::get('/healthcheck', function () {
    return response()->noContent(\Illuminate\Http\Response::HTTP_OK);
});

EOF;

        File::append($path, $append);

        $this->info('Published health check route successfully');

        $test = <<<'EOF'
<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function testHealthCheck()
    {
        $this->get('/api/healthcheck')->assertOk();
    }
}

EOF;

        File::put(base_path('tests/Feature/HealthCheckTest.php'), $test);

        $this->info('Published health check feature test successfully');
    }

    /**
     * Update the TrustProxies middleware to trust dynamic proxies.
     */
    protected function publishProxies()
    {
        $path = app_path('Http/Middleware/TrustProxies.php');

        $contents = File::get($path);

        $contents = Str::replace('protected $proxies;', "protected \$proxies = '*';", $contents);

        File::put($path, $contents);

        $this->info('Published trusted proxy changes successfully');
    }
}
