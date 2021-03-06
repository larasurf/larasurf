<?php

namespace LaraSurf\LaraSurf\Commands;

use Illuminate\Console\Command;
use LaraSurf\LaraSurf\Commands\Traits\DerivesAppUrl;

class Splash extends Command
{
    use DerivesAppUrl;

    protected $signature = 'larasurf:splash {--no-color}';

    protected $description = 'Show the LaraSurf splash screen';

    public function handle()
    {
        $with_color = !$this->option('no-color');

        $d = $with_color ? "\e[0;34m" : ''; // dark blue
        $l = $with_color ? "\e[1;34m" : ''; // light blue
        $w = $with_color ? "\e[1;37m" : ''; // white
        $r = $with_color ? "\e[0m" : ''; // reset
        $u = $with_color ? "\e[4m" : ''; // underline

        $url = self::deriveAppUrl();

        $url_length = strlen($url);
        $total_padding = 29 - $url_length;
        $padding_left = str_repeat(' ', floor($total_padding / 2));
        $padding_right = str_repeat(' ', ceil($total_padding / 2));
        $url_info = "${l}${padding_left}${u}${url}${r}${padding_right}${w}";

        $splash = <<<EOD
${w}╔═════════════════════════════╦════════════════════════════════════════════════╗
║                             ║${l}                ##############                  ${w}║
║                             ║${l}            ######################              ${w}║
║    ${r}Welcome to ${w}LaraSurf${r}!     ${w}║${l}          ##########################            ${w}║
║                             ║       ${l}################${d}####${l}##########           ${w}║
║                             ║      ${l}############${d}##############${l}#######         ${w}║
╠═════════════════════════════╣    ${l}############${d}#################${l}######         ${w}║
║                             ║   ${l}############${d}########              ${l}##         ${w}║
║                             ║  ${l}############${d}#######                           ${w}║
║      ${r}Your application       ${w}║  ${l}############${d}######                            ${w}║
║     ${r}has been generated      ${w}║ ${l}#############${d}######                            ${w}║
║  ${r}and is up and running at   ${w}║ ${l}#############${d}#######                           ${w}║
║${url_info}║${l}##############${d}#######                           ${w}║
║                             ║${l}###############${d}########                         ${w}║
║                             ║${l}################${d}##########                  ${l}##  ${w}║
╠═════════════════════════════╣${l}#################${d}##############         ${l}########${w}║
║                             ║${l}####################${d}####################${l}########${w}║
║                             ║${l}##########################${d}#########${l}#############${w}║
║    ${r}To get started, visit    ${w}║${l}################################################${w}║
║  ${l}${u}https://larasurf.com/docs${r}  ${w}║${l}################################################${w}║
║                             ║${l}################################################${w}║
║                             ║${l}################################################${w}║
╚═════════════════════════════╩════════════════════════════════════════════════╝${r}
EOD;

        $this->getOutput()->writeln($splash . "\x07"); // beep
    }
}
