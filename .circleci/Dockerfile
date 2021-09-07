FROM larasurf_base:latest

ARG WEB_ROOT=/var/www

RUN composer install --no-progress -d $WEB_ROOT && \
    composer clear-cache
