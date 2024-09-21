FROM php:8.1-fpm-buster

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    net-tools iproute2 \
    git unzip libzip-dev libicu-dev libonig-dev sqlite3 libsqlite3-dev && \
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    pecl install xdebug && docker-php-ext-enable xdebug && \
    apt-get install -y nodejs && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-install intl pdo_mysql zip bcmath pdo_sqlite

# Xdebug 設定は php.ini で管理するため、Dockerfileでの設定追加部分を削除
COPY ./php.ini /usr/local/etc/php/php.ini

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

WORKDIR /data
