<?php

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

    $file = '.docker/nginx/laravel.conf.template';

    $contents = file_exists($file) ? file_get_contents($file) : false;

    $url = $contents && strstr($contents, 'listen 443 ssl;') ? "https://localhost${r}" : "http://localhost${r}" . ' ';

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
║  ${r}and is up and running at   ${w_n}║${c_b}▀ÑXXh2╬▐ZÅ┘└ ¬≡T^█▀Ü▐[▌▐⌠▓${g_n}██${g_b}▄${g_n}████████▌      ${c_n};¿${c_b}▄▄${w_n}║
║     ${c_n}${u}${url}       ${w_n}║${c_b}▀b▒Ç▀▀▄▄▄▄▄╛▐▐▐▐⌠║Ü▐▐├╙X▀┘${g_b}▀${g_n}█████████${c_b}╖╖╤╤▄e▌▄▄└▄▄${w_n}║
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
    ];

    if (file_exists('.gitignore')) {
        $contents = array_map('trim', file('.gitignore', FILE_SKIP_EMPTY_LINES));

        $appends = [];

        foreach ($entries as $entry) {
            if (!in_array($entry, $contents)) {
                $appends[] = $entry;
            }
        }

        file_put_contents('.gitignore', implode(PHP_EOL, array_merge($appends, [''])), FILE_APPEND);

        echo '.gitignore modified' . PHP_EOL;
    } else {
        file_put_contents('.gitignore', implode(PHP_EOL, array_merge($entries, [''])));

        echo '.gitignore created' . PHP_EOL;
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
                file_put_contents('.php-cs-fixer.dist.php', $contents);

                echo '.php-cs-fixer.dist.php created' . PHP_EOL;
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
            'use_path_style_endpoint' => 'local' === env('APP_ENV'),
EOD;

            $contents = str_replace('\'endpoint\' => env(\'AWS_ENDPOINT\'),', $replace, $contents);
            $contents = str_replace('\'endpoint\' => env("AWS_ENDPOINT"),', $replace, $contents);

            file_put_contents('config/filesystems.php', $contents);

            echo 'config/filesystems.php modified' . PHP_EOL;
        }
    }
}

function publishEnvironmentFile() {
    foreach (['.env.example', '.env'] as $env_file) {
        if (file_exists($env_file)) {
            $contents = array_map('trim', file($env_file));

            foreach ($contents as &$content) {
                if (strpos($content, 'CACHE_DRIVER=') === 0) {
                    $content = 'CACHE_DRIVER=redis';
                }

                if (strpos($content, 'QUEUE_CONNECTION=') === 0) {
                    $content = 'QUEUE_CONNECTION=sqs';
                }

                if (strpos($content, 'SESSION_DRIVER=') === 0) {
                    $content = 'SESSION_DRIVER=redis';
                }
            }

            file_put_contents($env_file, implode(PHP_EOL, array_merge($contents, [''])));

            echo "$env_file modified" . PHP_EOL;
        }
    }
}

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the CLI";
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
        publishEnvironmentFile();
    } else if ($argv[1] === 'cs-fixer-config') {
        publishCodeStyleConfig();
    }
}
