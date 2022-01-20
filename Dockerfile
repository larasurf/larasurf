FROM php:8.0-alpine

ENV COMPOSER_VERSION 2.2.4
ENV COMPOSER_ALLOW_SUPERUSER 1
RUN until curl getcomposer.org --output /dev/null --silent; do echo 'Failed to curl getcomposer.org...' && sleep 1; done && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    until curl composer.github.io --output /dev/null --silent; do echo 'Failed to curl composer.github.io...' && sleep 1; done && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --version=${COMPOSER_VERSION} && \
    php -r "unlink('composer-setup.php');" && \
    composer --ansi --version --no-interaction && \
    apk add --no-cache \
        git \
        openssh \
        bash \
        make \
        zip \
        unzip \
        php8-bcmath \
        php8-ctype \
        php8-curl \
        php8-fileinfo \
        php8-imap \
        php8-intl \
        php8-json \
        php8-ldap \
        php8-mbstring \
        php8-openssl \
        php8-pdo_mysql \
        php8-pecl-igbinary \
        php8-pecl-msgpack \
        php8-tokenizer \
        php8-xml \
        php8-zip \
        php8-zlib \
        curl-dev

ARG WEB_ROOT=/var/www

WORKDIR $WEB_ROOT
