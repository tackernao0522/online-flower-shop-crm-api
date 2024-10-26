#!/bin/sh
set -e

echo "Starting entrypoint script"

# 適切な権限設定
chown -R www-data:www-data /var/www/storage
chmod -R 775 /var/www/storage

# キャッシュディレクトリのクリア
rm -rf /var/www/storage/framework/cache/*
rm -rf /var/www/storage/framework/views/*
rm -rf /var/www/storage/framework/sessions/*

# PHP-FPM設定の最適化（必要に応じて）
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

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# PHP-FPMの起動
php-fpm -D

# WebSocketサーバーの起動（backgroundで実行）
if [ "$RUN_WEBSOCKETS" = "true" ]; then
    echo "Starting WebSocket server on port 6001"
    php artisan websockets:serve --host=0.0.0.0 &
fi

# Nginxの起動
nginx -g 'daemon off;'
