#!/bin/sh
set -e

echo "Starting entrypoint script"

# ストレージディレクトリの準備と権限設定
mkdir -p /var/www/storage/framework/cache/data
chown -R www-data:www-data /var/www
chmod -R 775 /var/www/storage
chmod -R 775 /var/www/storage/framework/cache/data

# キャッシュディレクトリのクリア
rm -rf /var/www/storage/framework/cache/*
mkdir -p /var/www/storage/framework/cache/data  # キャッシュディレクトリを再作成
chmod -R 775 /var/www/storage/framework/cache/data
rm -rf /var/www/storage/framework/views/*
rm -rf /var/www/storage/framework/sessions/*

# PHP-FPM設定の最適化
sed -i "s/pm.max_children = .*/pm.max_children = 5/" /usr/local/etc/php-fpm.d/www.conf
sed -i "s/pm.start_servers = .*/pm.start_servers = 2/" /usr/local/etc/php-fpm.d/www.conf
sed -i "s/pm.min_spare_servers = .*/pm.min_spare_servers = 1/" /usr/local/etc/php-fpm.d/www.conf
sed -i "s/pm.max_spare_servers = .*/pm.max_spare_servers = 3/" /usr/local/etc/php-fpm.d/www.conf

# Laravel の起動準備
cd /var/www

# データベースのセットアップ
echo "Running database migrations"
php artisan migrate:fresh --force || { echo "Database migration failed"; exit 1; }

echo "Running database seeder"
php artisan db:seed --force || { echo "Database seeding failed"; exit 1; }

# Laravel キャッシュの設定
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# ストレージディレクトリの再確認と権限設定
mkdir -p /var/www/storage/framework/cache/data
chown -R www-data:www-data /var/www/storage/framework/cache/data
chmod -R 775 /var/www/storage/framework/cache/data

# PHP-FPMの起動
php-fpm -D

# WebSocketサーバーの起動
if [ "$RUN_WEBSOCKETS" = "true" ]; then
    echo "Starting WebSocket server on port 6001"
    php artisan websockets:serve --host=0.0.0.0 &
fi

# Nginxの起動
nginx -g 'daemon off;'
