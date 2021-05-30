#!/bin/bash

set -e

function exit_if_containers_not_running() {
  CONTAINERS_RUNNING="$(docker-compose ps -q)"

  if [[ -z "$CONTAINERS_RUNNING" ]]; then
    echo 'Containers are not running!'

    exit 1
  fi
}

if [[ "$1" == 'ssl' ]]; then
  if [[ "$2" == 'generate' ]]; then
    if [[ -n "$(which mkcert.exe)" ]]; then
      mkcert.exe -install && mkcert.exe -key-file .docker/ssl/local.pem -cert-file .docker/ssl/local.crt localhost
    elif [[ -n "$(which mkcert)" ]]; then
      mkcert -install && mkcert -key-file .docker/ssl/local.pem -cert-file .docker/ssl/local.crt localhost
    else
      echo 'To use local SSL, please install mkcert from: https://github.com/FiloSottile/mkcert'
      exit
    fi
  elif [[ "$2" == 'nginx-config' ]]; then
    if [[ -f '.docker/nginx/laravel.conf.template' ]] && ! grep -q 'listen 443 ssl;' '.docker/nginx/laravel.conf.template'; then
      cat << 'EOF' >> '.docker/nginx/laravel.conf.template'

server {
    listen 443 ssl;
    server_name localhost;
    ssl_certificate /var/ssl/local.crt;
    ssl_certificate_key /var/ssl/local.pem;
    ssl_protocols TLSv1.2;

    index index.php index.html;
    root /var/www/public;

    location / {
        try_files $uri /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass "${UPSTREAM_HOST}:${UPSTREAM_PORT}";
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }
}
EOF

      echo 'NGINX config template updated.'
      echo 'Rebuild the webserver image then restart the container!'
    elif grep -q 'listen 443 ssl;' '.docker/nginx/laravel.conf.template'; then
      echo 'NGINX config template already listens on port 443'
    else
      echo 'NGINX config template not found'
    fi
  else
    echo 'Unrecognized ssl command'
  fi
elif [[ "$1" == 'publish' ]]; then
  if [[ "$2" == 'cs-fixer-config' ]]; then
    docker-compose run --rm --no-deps laravel php ./vendor/larasurf/larasurf/src/surf.php publish cs-fixer-config
  else
    echo 'Unrecognized publish command'
  fi
elif [[ "$1" == 'composer' ]]; then
  cd $(pwd)
  docker-compose run --rm --no-deps laravel composer "${@:2}"
elif [[ "$1" == 'yarn' ]]; then
  cd $(pwd)
  docker-compose run --rm --no-deps laravel yarn "${@:2}"
elif [[ "$1" == 'artisan' ]]; then
  exit_if_containers_not_running

  cd $(pwd)
  docker-compose exec laravel php artisan "${@:2}"
elif [[ "$1" == 'test' ]]; then
  exit_if_containers_not_running

  cd $(pwd)
  docker-compose exec laravel ./vendor/bin/phpunit "${@:2}"
elif [[ "$1" == 'fix' ]]; then
  exit_if_containers_not_running

  if grep -q '"barryvdh/laravel-ide-helper"' 'composer.json'; then
    cd $(pwd)
    docker-compose exec laravel bash -c 'php artisan ide-helper:generate && php artisan ide-helper:meta && php artisan ide-helper:models --write-mixin'
  fi

  if grep -q '"friendsofphp/php-cs-fixer"' 'composer.json'; then
    cd $(pwd)
    docker-compose exec laravel ./vendor/bin/php-cs-fixer fix
  fi
elif [[ "$1" == '--help' ]]; then
  echo 'See: https://larasurf.com/docs'
else
  cd $(pwd)
  docker-compose "$@"
fi
