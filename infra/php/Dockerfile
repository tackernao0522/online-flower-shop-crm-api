FROM php:8.1-fpm-buster

ENV COMPOSER_ALLOW_SUPERUSER-1 \
    COMPOSER_HOME=/composer

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && \
    apt-get -y install --no-install-recommends git unzip libzip-dev libicu-dev libonig-dev sqlite3 libsqlite3-dev && \
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    apt-get install -y nodejs && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-install intl pdo_mysql zip bcmath pdo_sqlite

COPY ./php.ini /usr/local/etc/php/php.ini

WORKDIR /data
