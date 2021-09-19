<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait DerivesAppUrl
{
    /**
     * Determines the application url based upon if local SSL is enabled.
     *
     * @return string
     */
    protected static function deriveAppUrl() {
        $nginx_file = base_path('.docker/nginx/laravel.conf.template');
        $nginx_contents = File::exists($nginx_file) ? File::get($nginx_file) : false;
        $is_ssl = $nginx_contents && Str::contains($nginx_contents, 'listen 443 ssl;');
        $app_port_env_name = $is_ssl ? 'SURF_APP_SSL_PORT' : 'SURF_APP_PORT';
        $app_port = env($app_port_env_name);

        if (($is_ssl && $app_port === '443') || (!$is_ssl && $app_port === '80')) {
            $app_port = '';
        }

        if ($is_ssl) {
            $url = $app_port ? "https://localhost:${app_port}" : 'https://localhost';
        } else {
            $url = $app_port ? "http://localhost:${app_port}" : 'http://localhost';
        }

        return $url;
    }
}
