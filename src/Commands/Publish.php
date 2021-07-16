<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Commands\Traits\DerivesAppUrl;

class Publish extends Command
{
    use DerivesAppUrl;

    protected $signature = 'larasurf:publish {--cs-fixer} {--nginx-local-ssl} {--env-changes}';

    protected $description = 'Publish or make changes to various files as part of LaraSurf\'s post-install process';

    public function handle()
    {
        foreach ([
            'cs-fixer' => [$this, 'publishCsFixerConfig'],
            'nginx-local-ssl' => [$this, 'publishNginxLocalSslConfig'],
            'env-changes' => [$this, 'publishEnvChanges'],
                 ] as $option => $method) {
            if ($this->option($option)) {
                $method();
            }
        }
    }

    protected function publishCsFixerConfig()
    {
        $success = File::copy(__DIR__ . '/../../stubs/.php-cs-fixer.dist.php', base_path('.php-cs-fixer.dist.php'));

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
            $https_config = File::get(__DIR__ . '/../../stubs/nginx-https.conf');
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
}
