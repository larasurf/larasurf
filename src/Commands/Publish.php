<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Commands\Traits\DerivesAppUrl;

class Publish extends Command
{
    use DerivesAppUrl;

    protected $signature = 'larasurf:publish {--cs-fixer} {--nginx-local-ssl} {--env-changes} {--circleci-local} {--circleci-local-production}  {--circleci-local-stage-production} {--cloudformation}';

    protected $description = 'Publish or make changes to various files as part of LaraSurf\'s post-install process';

    public function handle()
    {
        foreach ([
            'cs-fixer' => [$this, 'publishCsFixerConfig'],
            'nginx-local-ssl' => [$this, 'publishNginxLocalSslConfig'],
            'env-changes' => [$this, 'publishEnvChanges'],
            'circleci-local' => [$this, 'publishCircleCiLocal'],
            'circleci-local-production' => [$this, 'publishCircleCiLocalProduction'],
            'circleci-local-stage-production' => [$this, 'publishCircleCiLocalStageProduction'],
            'cloudformation' => [$this, 'publishCloudFormation'],
                 ] as $option => $method) {
            if ($this->option($option)) {
                $method();
            }
        }
    }

    protected function publishCsFixerConfig()
    {
        $success = File::copy(__DIR__ . '/../../templates/.php-cs-fixer.dist.php', base_path('.php-cs-fixer.dist.php'));

        if ($success) {
            $this->info('Successfully published code style fixer config');
        } else {
            $this->error('Failed to publish code style fixer config');
        }
    }

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
                    $this->info('Successfully modified nginx config');
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
                                 'APP_URL=' => "APP_URL=$app_url",
                             ] as $find => $replace) {
                        if (strpos($content, $find) === 0) {
                            $content = $replace;
                        }
                    }
                }

                $success = File::put($env_file, implode(PHP_EOL, array_merge($contents, [''])));

                if ($success) {
                    $this->info("Successfully modified $file");
                } else {
                    $this->error("Failed to modify $file");
                }
            }
        }
    }

    protected function publishCircleCiLocal()
    {
        $this->publishCircleCi('config.local.yml');
    }

    protected function publishCircleCiLocalProduction()
    {
        $this->publishCircleCi('config.local-production.yml');
    }

    protected function publishCircleCiLocalStageProduction()
    {
        $this->publishCircleCi('config.local-stage-production.yml');
    }

    protected function publishCircleCi($filename)
    {
        if (!File::isDirectory(base_path('.circleci'))) {
            File::makeDirectory(base_path('.circleci'));
        }

        $circle_config_path = base_path('.circleci/config.yml');

        $success = true;

        if (File::exists($circle_config_path)) {
            $this->warn("File '.circleci/config.yml' already exists");
        } else {
            $success = File::copy(__DIR__ . "/../../templates/circleci/$filename", $circle_config_path);

            if ($success) {
                $this->info('Successfully published CircleCI configuration file');
            } else {
                $this->error('Failed to publish CircleCI configuration file');
            }
        }

        $docker_compose_path = base_path('.circleci/docker-compose.ci.yml');

        if (File::exists($docker_compose_path)) {
            $this->warn("File '.circleci/docker-compose.ci.yml' already exists");
        } else {
            $success = File::copy(__DIR__ . '/../../templates/circleci/docker-compose.ci.yml', $docker_compose_path);

            if ($success) {
                $this->info('Successfully published docker-compose file');
            } else {
                $this->error('Failed to publish docker-compose file');
            }
        }

        $dockerfile_path = base_path('.circleci/Dockerfile');

        if (File::exists($dockerfile_path)) {
            $this->warn("File '.circleci/Dockerfile' already exists");
        } else {
            $success = File::copy(__DIR__ . '/../../templates/circleci/Dockerfile', $dockerfile_path);

            if ($success) {
                $this->info('Successfully published Dockerfile file');
            } else {
                $this->error('Failed to publish Dockerfile file');
            }
        }
    }

    protected function publishCloudFormation()
    {
        if (!File::isDirectory(base_path('.cloudformation'))) {
            File::makeDirectory(base_path('.cloudformation'));
        }

        $infrastructure_template_path = base_path('.cloudformation/infrastructure.yml');

        if (File::exists($infrastructure_template_path)) {
            $this->warn("File '.cloudformation/infrastructure.yml' already exists");
        } else {
            $success = File::copy(__DIR__ . "/../../templates/cloudformation/infrastructure.yml", $infrastructure_template_path);

            if ($success) {
                $this->info('Successfully published infrastructure CloudFormation template');
            } else {
                $this->error('Failed to publish infrastructure CloudFormation template');
            }
        }

        // todo: publish app template file
    }
}
