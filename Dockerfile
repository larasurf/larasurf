FROM php:8.0-fpm-alpine

ENV COMPOSER_VERSION 2.1.11
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
        nodejs \
        npm \
        yarn \
        php8-bcmath \
        php8-ctype \
        php8-curl \
        php8-fileinfo \
        php8-gd \
        php8-iconv \
        php8-imap \
        php8-intl \
        php8-json \
        php8-ldap \
        php8-mbstring \
        php8-openssl \
        php8-pdo_mysql \
        php8-pecl-igbinary \
        php8-pecl-msgpack \
        php8-redis \
        php8-tokenizer \
        php8-xml \
        php8-zip \
        php8-zlib \
        icu-dev \
        curl-dev \
        freetype \
        libjpeg-turbo \
        libpng \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev && \
    docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ && \
    docker-php-ext-install -j$(nproc) gd && \
    docker-php-ext-enable gd && \
    apk del --no-cache freetype-dev libjpeg-turbo-dev libpng-dev && \
    apk add --no-cache --virtual .build-deps autoconf g++ make zlib-dev && \
    # ping workaround for docker DNS issues
    until ping -c1 pecl.php.net > /dev/null 2>&1; do :; done && \
    pecl channel-update pecl.php.net && \
    pecl install redis && \
    docker-php-ext-install pdo pdo_mysql iconv intl && \
    docker-php-ext-enable pdo_mysql redis iconv intl && \
    apk del .build-deps && \
    cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

ARG WEB_ROOT=/var/www

WORKDIR $WEB_ROOT
