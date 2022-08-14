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
    protected $signature = 'larasurf:publish {--vite-inertia} {--vite-livewire} {--vite-breeze-vue} {--vite-breeze-react} {--vite-breeze-blade} {--cs-fixer} {--nginx-local-tls} {--nginx-local-insecure} {--circleci} {--dusk} {--env-changes} {--awslocal} {--cloudformation} {--gitignore} {--healthcheck} {--proxies}';

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
                     'nginx-local-tls' => [$this, 'publishNginxLocalTlsConfig'],
                     'nginx-local-insecure' => [$this, 'publishNginxLocalInsecureConfig'],
                     'env-changes' => [$this, 'publishEnvChanges'],
                     'awslocal' => [$this, 'publishAwsLocalChanges'],
                     'circleci' => [$this, 'publishCircleCIConfig'],
                     'dusk' => [$this, 'publishDusk'],
                     'cloudformation' => [$this, 'publishCloudFormation'],
                     'gitignore' => [$this, 'publishGitIgnore'],
                     'healthcheck' => [$this, 'publishHealthCheck'],
                     'proxies' => [$this, 'publishProxies'],
                     'vite-inertia' => [$this, 'publishViteInertia'],
                     'vite-livewire' => [$this, 'publishViteLivewire'],
                     'vite-breeze-vue' => [$this, 'publishViteBreezeVue'],
                     'vite-breeze-react' => [$this, 'publishViteBreezeReact'],
                     'vite-breeze-blade' => [$this, 'publishViteBreezeBlade'],
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
     * Update the local NGINX configuration file to support TLS.
     */
    protected function publishNginxLocalTlsConfig()
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
     * Update the local NGINX configuration file to support Vite without TLS.
     */
    protected function publishNginxLocalInsecureConfig()
    {
        $nginx_config_path = base_path('.docker/nginx/laravel.conf.template');

        if (File::exists($nginx_config_path)) {
            $https_config = File::get(__DIR__ . '/../../templates/nginx-http.conf');
            $current_config = File::get($nginx_config_path);

            if (!Str::contains($current_config, 'listen 5173;')) {
                $new_config = $current_config . PHP_EOL . PHP_EOL . $https_config;

                $success = File::put($nginx_config_path, $new_config);

                if ($success) {
                    $this->info('Modified nginx config successfully');
                } else {
                    $this->error('Failed to modify nginx config');
                }
            } else {
                $this->warn('NGINX is already configured to listen on port 5173');
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

        $is_api_only = false;

        foreach (['.env', '.env.example'] as $file) {
            $env_file = base_path($file);

            if (File::exists($env_file)) {
                $contents = array_map('trim', file($env_file));

                if ($file === '.env') {
                    $app_url = $url;

                    if (in_array('FRONTEND_URL=http://localhost:3000', $contents)) {
                        $is_api_only = true;
                    }
                } else {
                    $app_url = Str::startsWith($url, 'https:')  ? 'https://localhost' : 'http://localhost';
                }

                $variables = [
                    'APP_URL=' => "$app_url",
                    'AWS_ENDPOINT=' => 'http://awslocal:4566',
                    'AWS_ACCESS_KEY_ID=' => 'laravel',
                    'AWS_SECRET_ACCESS_KEY=' => 'laravel',
                    'AWS_DEFAULT_REGION=' => 'us-east-1',
                    'AWS_BUCKET=' => 'laravel',
                    'AWS_USE_PATH_STYLE_ENDPOINT=' => 'true',
                    'AWS_URL=' => 'http://localhost:' . env('SURF_AWSLOCAL_PORT', '4566') . '/laravel',
                    'CACHE_DRIVER=' => 'redis',
                    'DB_CONNECTION=' => 'mysql',
                    'DB_DATABASE=' => 'laravel',
                    'DB_HOST=' => 'database',
                    'DB_PASSWORD=' => 'supersecret',
                    'DB_PORT=' => '3306',
                    'DB_USERNAME=' => 'laravel',
                    'MAIL_HOST=' => 'mail',
                    'MAIL_PORT=' => '1025',
                    'MAIL_FROM_ADDRESS=' => 'hello@example.com',
                    'MAIL_FROM_NAME=' => '${APP_NAME}',
                    'QUEUE_CONNECTION=' => 'sqs',
                    'REDIS_HOST=' => 'cache',
                    'REDIS_PORT=' => '6379',
                    'SESSION_DRIVER=' => 'redis',
                    'SQS_QUEUE=' => 'laravel',
                    'SQS_PREFIX=' => 'http://awslocal:4566/000000000000',
                ];

                if ($file === '.env.example' && $is_api_only) {
                    $variables['FRONTEND_URL='] = 'http://localhost:3000';
                }

                foreach ($variables as $find => $append) {
                    if (empty(array_filter($contents, fn ($content) => str_starts_with($content, $find)))) {
                        $contents[] = $find . $append;
                    } else {
                        foreach ($contents as &$content) {
                            if (str_starts_with($content, $find)) {
                                $content = $find . $append;
                            }
                        }
                    }
                }

                $success = File::put($env_file, implode(PHP_EOL, $contents) . PHP_EOL);

                if ($success) {
                    $this->info("Modified $file successfully");
                } else {
                    $this->error("Failed to modify $file");
                }
            }
        }
    }

    protected function publishDusk()
    {
        if (!File::isDirectory(base_path('.circleci'))) {
            File::makeDirectory(base_path('.circleci'));
        }

        $file_path = base_path('.circleci/docker-compose.ci.dusk.yml');

        if (File::exists($file_path)) {
            $this->warn("File '.circleci/docker-compose.ci.dusk.yml' exists");
        } else {
            $success = File::copy(__DIR__ . "/../../templates/circleci/docker-compose.ci.dusk.yml", $file_path);

            if ($success) {
                $this->info('Published CircleCI docker-compose file for Dusk successfully');
            } else {
                $this->error('Failed to publish CircleCI docker-compose file for Dusk');
            }
        }

        $docker_compose_file_path = base_path('docker-compose.yml');

        $contents = File::get($docker_compose_file_path);

        if (Str::contains($contents, '  chrome:')) {
            $this->warn("Service 'chrome' exists in docker-compose.yml");
        } else {
            $service = <<<EOD
services:
  chrome:
    image: 'selenium/standalone-chrome'
    volumes:
      - '/dev/shm:/dev/shm'

EOD;

            $dependencies = <<<EOD
      - cache
      - chrome
EOD;
            $contents = Str::replace('services:', $service, $contents);
            $contents = Str::replace('      - cache', $dependencies, $contents);

            $success = File::put($docker_compose_file_path, $contents);

            if ($success) {
                $this->info('Published chrome service in docker-compose.yml file successfully');
            } else {
                $this->error('Failed to publish chrome service in docker-compose.yml file');
            }
        }
    }

    /**
     * Update the S3 filesystem configuration to override the base URL for temporary URLs.
     */
    protected function publishAwsLocalChanges()
    {
        $file_path = base_path('config/filesystems.php');

        $contents = File::get($file_path);

        if (!Str::contains($contents, "'temporary_url' =>")) {
            $replace = <<<EOD
'url' => env('AWS_URL'),
            'temporary_url' => env('AWS_URL'),
EOD;

            foreach ([
                "'url' => env('AWS_URL'),",
                "'url' => env(\"AWS_URL\"),",
                     ] as $find) {
                $contents = Str::replace($find, $replace, $contents);
            }

            $success = File::put($file_path, $contents);

            if ($success) {
                $this->info("Modified $file_path successfully");
            } else {
                $this->error("Failed to modify $file_path");
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

        $file_path = base_path('.circleci/inject-secrets.sh');

        if (File::exists($file_path)) {
            $this->warn("File '.circleci/inject-secrets.sh' exists");
        } else {
            $success = File::copy(__DIR__ . "/../../templates/circleci/inject-secrets.sh", $file_path);

            if ($success) {
                $this->info('Published CircleCI inject secrets script successfully');
            } else {
                $this->error('Failed to publish CircleCI inject secrets script');
            }
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
     * Publish the updated vite.config.js file for Jetsream Inertia.
     */
    protected function publishViteInertia()
    {
        $this->publishVite('vite.config.inertia.js');
    }

    /**
     * Publish the updated vite.config.js file for Jetsream Livewire.
     */
    protected function publishViteLivewire()
    {
        $this->publishVite('vite.config.livewire.js');
    }

    /**
     * Publish the updated vite.config.js file for Breeze Vue.
     */
    protected function publishViteBreezeVue()
    {
        $this->publishVite('vite.config.breeze.vue.js');
    }

    /**
     * Publish the updated vite.config.js file for Breeze React.
     */
    protected function publishViteBreezeReact()
    {
        $this->publishVite('vite.config.breeze.react.js');
    }

    /**
     * Publish the updated vite.config.js file for Breeze Blade.
     */
    protected function publishViteBreezeBlade()
    {
        $this->publishVite('vite.config.breeze.blade.js');
    }

    /**
     * Publish the updated vite.config.js file.
     *
     * @param string $template_name
     * @return void
     */
    protected function publishVite(string $template_name)
    {
        $contents = File::get(__DIR__ . "/../../templates/vite/$template_name");

        if (!File::put(base_path('vite.config.js'), $contents)) {
            $this->error('Failed to publish vite.config.js');
        } else {
            $this->info('Published vite.config.js successfully');
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

        if (File::exists($path)) {
            $contents = File::get($path);

            $contents = Str::replace('protected $proxies;', "protected \$proxies = '*';", $contents);

            File::put($path, $contents);

            $this->info('Published trusted proxy changes successfully');
        } else {
            $this->warn("File '$path' does not exist");
        }
    }
}
