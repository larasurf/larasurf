#!/bin/bash

set -e

if [[ "$1" == "ssl" ]]; then
  if [[ "$2" == "generate" ]]; then
    if [[ -n "$(which mkcert.exe)" ]]; then
      SSL_COMMAND="mkcert.exe"
    elif [[ -n "$(which mkcert)" ]]; then
      SSL_COMMAND="mkcert"
    else
      echo "To use local SSL, please install mkcert from: https://github.com/FiloSottile/mkcert"
      exit
    fi

    eval "$SSL_COMMAND -install && $SSL_COMMAND -key-file .docker/ssl/local.pem -cert-file .docker/ssl/local.crt localhost"
  else
    echo "Unrecognized ssl command"
  fi
elif [[ "$1" == "artisan" ]]; then
  cd $(pwd)
  docker-compose exec laravel php artisan "${@:2}"
  cd $(pwd)
elif [[ "$1" == "composer" ]]; then
  cd $(pwd)
  docker-compose run --rm --no-deps laravel composer "${@:2}"
  cd $(pwd)
elif [[ "$1" == "yarn" ]]; then
  cd $(pwd)
  docker-compose exec laravel yarn "${@:2}"
  cd $(pwd)
elif [[ "$1" == "phpunit" ]]; then
  cd $(pwd)
  docker-compose exec laravel ./vendor/bin/phpunit "${@:2}"
  cd $(pwd)
elif [[ "$1" == "--help" ]]; then
  echo 'See: https://larasurf.com/docs'
else
  cd $(pwd)
  docker-compose "$@"
  cd $(pwd)
fi
