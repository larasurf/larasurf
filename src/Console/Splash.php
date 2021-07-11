<?php

namespace LaraSurf\LaraSurf\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Splash extends Command
{
    protected $signature = 'larasurf:splash {--no-color}';

    protected $description = 'Show the LaraSurf splash image';

    public function handle()
    {
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
        $with_color = !$this->option('no-color');

        $c_n = $with_color ? "\033[36;49m" : ''; // cyan no background
        $c_g = $with_color ? "\033[36;47m" : ''; // cyan gray background
        $c_b = $with_color ? "\033[36;44m" : ''; // cyan blue background
        $g_n = $with_color ? "\033[37;49m" : ''; // gray no background
        $g_b = $with_color ? "\033[37;44m" : ''; // gray blue background
        $w_n = $with_color ? "\033[97;49m" : ''; // white no background
        $r = $with_color ? "\033[0m" : ''; // reset
        $u = $with_color ? "\033[4m" : ''; // underline

        $url = $this->deriveAppUrl();

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

    protected function deriveAppUrl() {
        $nginx_file = '.docker/nginx/laravel.conf.template';
        $nginx_contents = File::exists($nginx_file) ? File::get($nginx_file) : false;
        $is_ssl = $nginx_contents && Str::contains($nginx_contents, 'listen 443 ssl;');
        $app_port_search = $is_ssl ? 'SURF_APP_SSL_PORT=' : 'SURF_APP_PORT=';
        $env_file = '.env';
        $env_contents = File::exists($env_file) ? array_map('trim', file($env_file)) : false;
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
}
