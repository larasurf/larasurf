<?php

function echoSplashImage(array $message_names, $with_color = true) {
    if (in_array('running', $message_names)) {
        $message_names = array_filter($message_names, function ($message_name) {
            return $message_name !== 'running';
        });

        $contents = file_get_contents('.docker/nginx/laravel.conf.template');

        if (strstr($contents, 'listen 443 ssl;')) {
            $message_names[] = 'running-https';
        } else {
            $message_names[] = 'running-http';
        }
    }

    $c = $with_color ? "\033[36m" : '';
    $y = $with_color ? "\033[33m" : '';
    $w = $with_color ? "\033[97m" : '';
    $n = $with_color ? "\033[0m" : '';
    $d = $with_color ? "\033[2m" : '';
    $u = $with_color ? "\033[4m" : '';

    $splash = <<<EOD
${w}╔══════════════════════════════════════════════════════════════════════════════╗
${w}║$y    ##############    ####        #####   ###############     #############   ${w}║
║$y   #####      #####   ####        #####   ####       #####    ####            ${w}║
║$y   #########          ####        #####   ####        ####    ####            ${w}║
║$y     #############    ####        $y#####   #############       ##########      ${w}║
║$y               ####   ####        $y####    ####      ######    ####            ${w}║
║$c   $y######    ######    $c%$y#############     ####  $c%     $y####    ####        $c%   ${w}║
║$c%    $y############    $c%%% $y##########       ####$c%%%     $y####    ####      $c%%%   ${w}║
║$c%%%                %%%%%%%                  %%%%%%%                   %%%%%%% ${w}║
║$c%%%%%%%       %%%%%%%%%%%%%%%          %%%%%%%%%%%%%               %%%%%%%%%%%${w}║
║$c%%%%%%%%%% %%%%%%%%%%%%%%%%%%%%%  %%%%%%%%%%%%%%%%%%%%%%%%%  %%%%%%%%%%%%%%%%%${w}║
║$c%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%${w}║
║$c%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%${w}║
╠══════════════════════════════════════════════════════════════════════════════╣
$n
EOD;

    $messages = [
        'welcome' => "${w}║                            ${d}Welcome to $n${y}LaraSurf${n}!                              ${w}║$n",
        'running-http' => "${w}║           ${d}Your application is up and running at $n$w${u}http://localhost$n$w             ║$n",
        'running-https' => "${w}║           ${d}Your application is up and running at $n$w${u}https://localhost$n$w            ║$n",
        'docs' => "${w}║                ${d}See $n$c${u}https://larasurf.com/docs$n$d to get started!$n$w                 ║$n",
    ];

    foreach ($message_names as $message_name) {
        if (isset($messages[$message_name])) {
            $splash .= $messages[$message_name] . PHP_EOL;
        }
    }

    $splash .= "${w}╚══════════════════════════════════════════════════════════════════════════════╝$n" . PHP_EOL;

    echo $splash;
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
        '.php_cs.cache',
        '.phpstorm.meta.php',
        '_ide_helper*',
    ];

    if (file_exists('.gitignore')) {
        $contents = array_map('trim', file('.gitignore', FILE_SKIP_EMPTY_LINES));

        var_export($contents);

        $appends = [];

        foreach ($entries as $entry) {
            if (!in_array($entry, $contents)) {
                echo "'$entry' not found" . PHP_EOL;
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
        if (file_exists('.php_cs.dist')) {
            echo '.php_cs.dist already exists' . PHP_EOL;
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
        'trailing_comma_in_multiline_array' => true,

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
                file_put_contents('.php_cs.dist', $contents);

                echo '.php_cs.dist created' . PHP_EOL;
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

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the CLI";
    die(1);
}

array_shift($argv);

if (isset($argv[0]) && $argv[0] === 'splash' && isset($argv[1])) {
    $message_names = array_values(array_unique(array_map('trim', explode(',', $argv[1]))));

    echoSplashImage($message_names);
} else if (isset($argv[0]) && $argv[0] === 'publish') {
    publishGitignore();
    publishFilesystem();
    publishCodeStyleConfig();
}
