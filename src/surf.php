<?php

function echoSplashImage($message_name = null, $with_ssl = true, $with_color = true) {
    $c = $with_color ? "\033[36m" : '';
    $y = $with_color ? "\033[33m" : '';
    $w = $with_color ? "\033[97m" : '';
    $n = $with_color ? "\033[0m" : '';
    $d = $with_color ? "\033[2m" : '';
    $u = $with_color ? "\033[4m" : '';

    $application_url = $with_ssl ? 'https://localhost' : "http://localhost$n ";

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
║                            ${d}Welcome to $n${y}LaraSurf${n}!                              ${w}║
$n
EOD;

    if ($message_name === 'complete') {
        $splash .= "${w}║           ${d}Your application is up and running at $n$w$u$application_url$n$w            ║$n" . PHP_EOL;
        $splash .= "${w}║                ${d}See $n$c${u}https://larasurf.com/docs$n$d to get started!$n$w                 ║$n" . PHP_EOL;
    }

    $splash .= "${w}╚══════════════════════════════════════════════════════════════════════════════╝$n" . PHP_EOL;

    echo $splash;
}

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the CLI";
    die(1);
}

array_shift($argv);

if (isset($argv[0]) && $argv[0] === 'splash' && isset($argv[1])) {
    $with_ssl = isset($argv[2]) && $argv[2] === '--ssl';

    echoSplashImage($argv[1], $with_ssl);
}
