#!/usr/bin/env bash

set -e

ERROR='\033[91m'
SUCCESS='\033[92m'
RESET='\033[0m'

function exit_if_containers_not_running() {
  CONTAINERS_RUNNING="$(docker-compose ps -q)"

  if [[ -z "$CONTAINERS_RUNNING" ]]; then
    echo -e "${ERROR}Containers are not running!${RESET}"

    exit 1
  fi
}

if [[ "$1" == 'ssl' ]]; then
  if [[ "$2" == 'generate-certificate' ]]; then
    if [[ -n "$(which mkcert.exe)" ]]; then
      mkcert.exe -install && mkcert.exe -key-file .docker/ssl/local.pem -cert-file .docker/ssl/local.crt localhost
    elif [[ -n "$(which mkcert)" ]]; then
      mkcert -install && mkcert -key-file .docker/ssl/local.pem -cert-file .docker/ssl/local.crt localhost
    else
      echo -e "${ERROR}To use local SSL, please install mkcert from: https://github.com/FiloSottile/mkcert${RESET}"
      exit 1
    fi
  else
    echo -e "${ERROR}Unrecognized ssl command${RESET}"
  fi
elif [[ "$1" == 'composer' ]]; then
  cd $(pwd)
  docker-compose run --rm --no-deps laravel composer "${@:2}"
elif [[ "$1" == 'yarn' ]]; then
  cd $(pwd)
  docker-compose run --rm --no-deps laravel yarn "${@:2}"
elif [[ "$1" == 'aws' ]]; then
  docker run --rm -it -v ~/.aws:/root/.aws amazon/aws-cli:2.0.6 "${@:2}"
elif [[ "$1" == 'awslocal' ]]; then
  exit_if_containers_not_running

  NETWORK_NAME="$(basename $(pwd))_default"

  docker run --rm -it --network="$NETWORK_NAME" -e AWS_DEFAULT_REGION=us-east-1 -e AWS_ACCESS_KEY_ID=local -e AWS_SECRET_ACCESS_KEY=local amazon/aws-cli:2.0.6 --endpoint http://awslocal:4566 "${@:2}"
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
elif [[ "$1" == 'fresh' ]]; then
  REFRESH_COMMAND='php artisan migrate'

  if [[ "$2" == '--seed' ]]; then
    REFRESH_COMMAND="$REFRESH_COMMAND --seed"
  elif [[ -n "$2" ]]; then
    echo -e "${ERROR}Unrecognized option '$2'${RESET}"

    exit 1
  fi

  if [[ -f '.env' ]]; then
    DB_PORT=$(cat .env | grep SURF_DB_PORT= | sed s/SURF_DB_PORT=//)
  fi

  if [[ -z "$DB_PORT" ]]; then
    DB_PORT=3306
  fi

  cd $(pwd)
  docker-compose down --volumes
  cd $(pwd)
  docker-compose up -d

  until curl localhost:$DB_PORT --http0.9 --output /dev/null --silent
  do
      {
        echo 'Waiting for database to be ready...'
        ((COUNT++)) && ((COUNT==20)) && echo -e "${ERROR}Could not connect to database after 20 tries!${RESET}" && exit 1
        sleep 3
      } 1>&2
  done

  echo 'Database is ready!'

  cd $(pwd)
  docker-compose exec laravel $REFRESH_COMMAND
elif [[ "$1" == '--help' ]] || [[ "$1" == 'help' ]]; then
  echo 'See: https://larasurf.com/docs'
else
  cd $(pwd)
  docker-compose "$@"
fi
