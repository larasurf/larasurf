#!/bin/bash

set -e

POST_INSTALL_CMD="php ./vendor/larasurf/larasurf/src/surf.php publish && php artisan migrate --force"

if grep -q "\"barryvdh/laravel-ide-helper\"" "composer.json" then
  POST_INSTALL_CMD="$POST_INSTALL_CMD && php artisan ide-helper:generate && php artisan ide-helper:meta && php artisan ide-helper:models --write-mixin"
fi

if grep -q "\"friendsofphp/php-cs-fixer\"" "composer.json" then
  POST_INSTALL_CMD="$POST_INSTALL_CMD && ./vendor/bin/php-cs-fixer fix"
fi

POST_INSTALL_CMD="$POST_INSTALL_CMD && php ./vendor/larasurf/larasurf/src/surf.php splash welcome,running,docs"

while ! curl localhost:${FORWARD_DB_PORT:-3306} --http0.9 --output /dev/null --silent
do
    {
      echo "Waiting for MySQL to be ready..."
      ((COUNT++)) && ((COUNT==20)) && echo "Could not connect to MySQL after 20 tries!" && exit
      sleep 3
    } 1>&2
done

cd $(pwd)
docker-compose exec laravel bash -c "$POST_INSTALL_CMD"
cd $(pwd)
