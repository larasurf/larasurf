FROM php:8.1-alpine

ENV COMPOSER_VERSION 2.5.4
ENV COMPOSER_ALLOW_SUPERUSER 1
RUN until curl getcomposer.org --output /dev/null --silent; do echo 'Failed to curl getcomposer.org...' && sleep 1; done && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    until curl composer.github.io --output /dev/null --silent; do echo 'Failed to curl composer.github.io...' && sleep 1; done && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --version=${COMPOSER_VERSION} && \
    php -r "unlink('composer-setup.php');" && \
    apk add --no-cache git openssh bash make zip unzip && \
    curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s \
              bcmath ctype curl fileinfo imap intl json ldap mbstring openssl pdo_mysql igbinary msgpack tokenizer xml zip zlib

ARG WEB_ROOT=/var/www

WORKDIR $WEB_ROOT
