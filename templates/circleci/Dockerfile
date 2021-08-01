ARG BASE_IMAGE

FROM ${BASE_IMAGE}

ARG WEB_ROOT=/var/www

RUN --mount=type=ssh composer install --no-progress -d $WEB_ROOT && \
    composer clear-cache
