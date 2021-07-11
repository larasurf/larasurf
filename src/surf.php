<?php

function deriveAppUrl() {
    $nginx_file = '.docker/nginx/laravel.conf.template';

    $nginx_contents = file_exists($nginx_file) ? file_get_contents($nginx_file) : false;

    $is_ssl = $nginx_contents && strstr($nginx_contents, 'listen 443 ssl;');

    $app_port_search = $is_ssl ? 'SURF_APP_SSL_PORT=' : 'SURF_APP_PORT=';

    $env_file = '.env';

    $env_contents = file_exists($env_file) ? array_map('trim', file($env_file)) : false;

    $app_port = '';

    if ($env_contents) {
        foreach ($env_contents as $env_content) {
            if (strpos($env_content, $app_port_search) === 0) {
                $app_port = str_replace($app_port_search, '', $env_content);

                break;
            }
        }
    }

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

function echoSplashImage($with_color = true) {
/*
╔═════════════════════════════╦════════════════════════════════════════════════╗
║                             ║                                                ║
║                             ║                   ▄▄▄▄████▄▄▄                  ║
║    Welcome to LaraSurf!     ║                ▄███████████████▄               ║
║                             ║             ▄████████████████████▄             ║
║                             ║            ▄███████████████████████▄           ║
╠═════════════════════════════╣           ███████████████████████████          ║
║                             ║          ▐████████████████████████████         ║
║                             ║╤▄,       ████████▀▀▀▀▀╙╙╙▀▀▀▀█████████         ║
║      Your application       ║Å▐▀2▄     █████▀T  ;└*∞ⁿ ┌└┘*═*▀███████         ║
║     has been generated      ║▌)╙½½Q▄   ▐▀▀▄═  └▄▄¢▄▄╤Ä▄▌µJ▐█▄j▀████          ║
║  and is up and running at   ║▀ÑXXh2╬▐ZÅ┘└ ¬≡T^█▀Ü▐[▌▐⌠▓██▄████████       ;¿▄▄║
║     https://localhost       ║▀b▒Ç▀▀▄▄▄▄▄╛▐▐▐▐⌠║Ü▐▐├╙X▀┘▀█████████╖╖╤╤▄e▌▄▄└▄▄║
║                             ║ ▐j=▐ ▐ ▐ ▐ ▐▐"╙▐▐▌¼¼XN,' ═▄▀▀▀▀▀▀v«╧▀▀çÄ╩░Y╙▀╧▀║
║                             ║  "═▐ ▐  ▄ W'▄Y ╙╙╙▌▄² ╙*Φ╛T╧wÅÜÅ╝╧;╓▄e*T└└└7*w ║
╠═════════════════════════════╣   ▀ ▄ ▌ ▐ ╘.└╕   ,*▄      └└└└└└└╓µ∞rⁿ└└└└└²*  ║
║                             ║     └Ç ╕ ▀,╙▄ ╘▄ └*w¿└└        ", ;¿∞*ⁿ7²7ⁿ*   ║
║                             ║         ╙.└X└X¿ ╙Y▄   └└└└└└└  ,;;;;;,         ║
║    To get started, visit    ║       ⁿΓ2ZZJ*∞▄▀«w╓└╙ⁿⁿⁿ**   ".   ≡∞4Q╤┘'      ║
║  https://larasurf.com/docs  ║          ⁿ777≡c*wJ╤▄╕▄ÄÅ└└└└└└²Φ╗≡▄▄╘└         ║
║                             ║            ':└²»▄½f∞,╘▀º*╝╩PPw+─ ⁿ└            ║
║                             ║                          '└└`                  ║
╚═════════════════════════════╩════════════════════════════════════════════════╝
 */
    $c_n = $with_color ? "\033[36;49m" : ''; // cyan no background
    $c_g = $with_color ? "\033[36;47m" : ''; // cyan gray background
    $c_b = $with_color ? "\033[36;44m" : ''; // cyan blue background
    $g_n = $with_color ? "\033[37;49m" : ''; // gray no background
    $g_b = $with_color ? "\033[37;44m" : ''; // gray blue background
    $w_n = $with_color ? "\033[97;49m" : ''; // white no background
    $r = $with_color ? "\033[0m" : ''; // reset
    $u = $with_color ? "\033[4m" : ''; // underline

    $url = deriveAppUrl();

    $url_length = strlen($url);
    $total_padding = 29 - $url_length;
    $padding_left = str_repeat(' ', floor($total_padding / 2));
    $padding_right = str_repeat(' ', ceil($total_padding / 2));
    $url_info = "${c_n}${padding_left}${u}${url}${r}${padding_right}${w_n}";

    $splash = <<<EOD
${w_n}╔═════════════════════════════╦════════════════════════════════════════════════╗
║                             ║                                                ║
║                             ║                   ${g_n}▄▄▄█████▄▄▄${w_n}                  ║
║    ${r}Welcome to ${w_n}LaraSurf!     ║                ${g_n}▄███████████████▄${w_n}               ║
║                             ║             ${g_n}▄████████████████████▄             ${w_n}║
║                             ║            ${g_n}▄███████████████████████▄          ${w_n} ║
╠═════════════════════════════╣           ${g_n}███████████████████████████          ${w_n}║
║                             ║          ${g_n}▐███████████████████████████▌         ${w_n}║
║                             ║${c_b}╤${c_n}▄,${r}       ${g_n}████████${g_b}▀▀▀▀▀${c_b}╙╙╙${g_b}▀▀▀▀${g_n}█████████         ${w_n}║
║      ${r}Your application       ${w_n}║${c_b}Å▐▀2▄${r}     ${g_n}█████${g_b}▀${c_b}T  ;└*∞ⁿ ┌└┘*═*${g_b}▀${g_n}███████         ${w_n}║
║     ${r}has been generated      ${w_n}║${c_b}▌)╙½½Q▄${r}   ${g_n}▐${g_b}▀▀${c_g}▄${c_b}═  └▄▄¢▄▄╤Ä▄▌µJ${g_b}▐█▄${c_b}j${g_b}▀${g_n}████▌         ${w_n}║
║    ${r}and is accessible at     ${w_n}║${c_b}▀ÑXXh2╬▐ZÅ┘└ ¬≡T^█▀Ü▐[▌▐⌠▓${g_n}██${g_b}▄${g_n}████████▌      ${c_n};¿${c_b}▄▄${w_n}║
║${url_info}║${c_b}▀b▒Ç▀▀▄▄▄▄▄╛▐▐▐▐⌠║Ü▐▐├╙X▀┘${g_b}▀${g_n}█████████${c_b}╖╖╤╤▄e▌▄▄└▄▄${w_n}║
║                             ║ ${c_n}▐${c_b}j=▐ ▐ ▐ ▐ ▐▐"╙▐▐▌¼¼XN,' ═▄${g_b}▀▀▀▀▀▀${c_b}v«╧▀▀çÄ╩░Y╙▀╧▀${w_n}║
║                             ║  ${c_b}"═▐ ▐  ▄ W'▄Y ╙╙╙▌▄² ╙*Φ╛T╧wÅÜÅ╝╧;╓▄e*T└└└7*w${w_n} ║
╠═════════════════════════════╣   ${c_b}▀ ▄ ▌ ▐ ╘.└╕   ,*▄      └└└└└└└╓µ∞rⁿ└└└└└²*${w_n}  ║
║                             ║    ${c_n}`${c_b}└Ç ╕ ▀,╙▄ ╘▄ └*w¿└└        ", ;¿∞*ⁿ7²7ⁿ${c_n}*${w_n}   ║
║                             ║      ${c_b}   ╙.└X└X¿ ╙Y▄   └└└└└└└  ,;;;;;,    ${w_n}     ║
║  ${r}For more information, see  ${w_n}║       ${c_n}ⁿ${c_b}Γ2ZZJ*∞▄▀«w╓└╙ⁿⁿⁿ**   ".   ≡∞4Q╤┘'${w_n}      ║
║  ${c_n}${u}https://larasurf.com/docs${r}${w_n}  ║          ${c_n}ⁿ${c_b}777≡c*wJ╤▄╕▄ÄÅ└└└└└└²Φ╗≡▄▄╘└ ${c_n}'       ${w_n}║
║                             ║            ${c_n}'${c_b}:└²»▄½f∞,╘▀º*╝╩PPw+─ ⁿ└${c_n}'           ${w_n}║
║                             ║                  ${c_b}        '└└`${w_n}                  ║
╚═════════════════════════════╩════════════════════════════════════════════════╝
EOD;

    echo $splash . PHP_EOL;
}

function publishGitignore() {
    $entries = [
        '.idea',
        'desktop.ini',
        'thumbs.db',
        '.DS_STORE',
        'public/js',
        'public/css',
        'public/build',
        'public/mix-manifest.json',
        'clover.xml',
        'storage/coverage',
        'storage/test-results',
        '.php-cs-fixer.cache',
        '.phpstorm.meta.php',
        '_ide_helper*',
        'docker-compose.override.yml',
    ];

    if (file_exists('.gitignore')) {
        $contents = array_map('trim', file('.gitignore', FILE_SKIP_EMPTY_LINES));

        $appends = [];

        foreach ($entries as $entry) {
            if (!in_array($entry, $contents)) {
                $appends[] = $entry;
            }
        }

        $result = file_put_contents('.gitignore', implode(PHP_EOL, array_merge($appends, [''])), FILE_APPEND);

        if ($result !== false) {
            echo 'Modified .gitignore' . PHP_EOL;
        } else {
            echo 'Failed to modify .gitignore' . PHP_EOL;
        }
    } else {
        $result = file_put_contents('.gitignore', implode(PHP_EOL, array_merge($entries, [''])));

        if ($result !== false) {
            echo 'Created .gitignore' . PHP_EOL;
        } else {
            echo 'Failed to create .gitignore' . PHP_EOL;
        }
    }
}

function publishCodeStyleConfig() {
    if (file_exists('composer.json')) {
        if (file_exists('.php-cs-fixer.dist.php')) {
            echo '.php-cs-fixer.dist.php already exists' . PHP_EOL;
        } else {
            $composer_contents = file_get_contents('composer.json');

            if (strstr($composer_contents, '"friendsofphp/php-cs-fixer"')) {
                $contents = <<<EOD
<?php

\$finder = (new PhpCsFixer\Finder())
    ->files()
    ->name("*.php")
    ->in("app")
    ->in("bootstrap")
    ->in("config")
    ->in("database")
    ->in("tests")
    ->exclude("cache");

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'simplified_null_return' => false,
        'is_null' => false,
        'single_quote' => true,
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag' => true,
        'unary_operator_spaces' => true,
        'blank_line_before_statement' => false,
        'trailing_comma_in_multiline' => true,

        // PHPDOC Rules
        'phpdoc_align' => false,
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_order' => true,
        'phpdoc_separation' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'no_superfluous_phpdoc_tags' => false,
        'single_trait_insert_per_statement' => false
    ])
    ->setFinder(\$finder);

EOD;
                $result = file_put_contents('.php-cs-fixer.dist.php', $contents);

                if ($result !== false) {
                    echo '.php-cs-fixer.dist.php created' . PHP_EOL;
                } else {
                    echo 'Failed to create .php-cs-fixer.dist.php' . PHP_EOL;
                }
            } else {
                echo 'php-cs-fixer not installed' . PHP_EOL;
            }
        }
    }
}

function publishFilesystem() {
    if (file_exists('config/filesystems.php')) {
        $contents = file_get_contents('config/filesystems.php');

        if (preg_match('/\'disks\' =>[\s\S]+\'s3\' => [\s\S]+\'bucket_endpoint\' =>/', $contents)) {
            echo 'config/filesystems.php has already been modified' . PHP_EOL;
        } else {
            $replace = <<<EOD
'endpoint' => env('AWS_ENDPOINT'),
            'bucket_endpoint' => false,
EOD;

            $contents = str_replace('\'endpoint\' => env(\'AWS_ENDPOINT\'),', $replace, $contents);
            $contents = str_replace('\'endpoint\' => env("AWS_ENDPOINT"),', $replace, $contents);

            $result = file_put_contents('config/filesystems.php', $contents);

            if ($result !== false) {
                echo 'Modified config/filesystems.php' . PHP_EOL;
            } else {
                echo 'Failed to modify config/filesystems.php' . PHP_EOL;
            }

        }
    }
}

function publishEnvironmentFiles()
{
    $url = deriveAppUrl();

    foreach (['.env', '.env.example'] as $env_file) {
        if (file_exists($env_file)) {
            $contents = array_map('trim', file($env_file));

            if ($env_file === '.env') {
                $app_url = $url;
            } else {
                $app_url = $env_file === '.env.example' && strstr($url, 'https:')
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

            $result = file_put_contents($env_file, implode(PHP_EOL, array_merge($contents, [''])));

            if ($result !== false) {
                echo "Modified $env_file" . PHP_EOL;
            } else {
                echo "Failed to modify $env_file" . PHP_EOL;
            }
        }
    }
}

function publishSslNginxConfig() {
    $http_config = <<<EOD
server {
    listen 80;
    index index.php index.html;
    root /var/www/public;

    location / {
        try_files \$uri /index.php?\$args;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass "\${UPSTREAM_HOST}:\${UPSTREAM_PORT}";
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
}

EOD;

    $https_config = <<<EOD

server {
    listen 443 ssl;
    server_name localhost;
    ssl_certificate /var/ssl/local.crt;
    ssl_certificate_key /var/ssl/local.pem;
    ssl_protocols TLSv1.2;

    index index.php index.html;
    root /var/www/public;

    location / {
        try_files \$uri /index.php?\$args;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass "\${UPSTREAM_HOST}:\${UPSTREAM_PORT}";
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
}

EOD;

    $file = '.docker/nginx/laravel.conf.template';

    if (file_exists($file)) {
        $contents = file_get_contents($file);

        if (!strstr($contents, 'listen 443 ssl;')) {
            $result = file_put_contents($file, $https_config, FILE_APPEND);

            if ($result !== false) {
                echo "Modified $file" . PHP_EOL;
            } else {
                echo "Failed to modify $file" . PHP_EOL;
            }
        } else {
            echo 'NGINX config template is already listening on 443' . PHP_EOL;
        }
    } else {
        $config = $http_config . PHP_EOL . PHP_EOL . $https_config;

        $result = file_put_contents($file, $config);

        if ($result !== false) {
            echo "Created $file" . PHP_EOL;
        } else {
            echo "Failed to create $file" . PHP_EOL;
        }
    }
}

if (php_sapi_name() !== 'cli') {
    echo 'This script must be run from the CLI';
    die(1);
}

array_shift($argv);

if (isset($argv[0]) && $argv[0] === 'splash') {
    echoSplashImage();
} else if (isset($argv[0]) && $argv[0] === 'publish') {
    if (empty($argv[1])) {
        publishGitignore();
        publishFilesystem();
        publishCodeStyleConfig();
        publishEnvironmentFiles();
    } else if ($argv[1] === 'cs-fixer-config') {
        publishCodeStyleConfig();
    } else if ($argv[1] === 'ssl-nginx-config') {
        publishSslNginxConfig();
    }
}
