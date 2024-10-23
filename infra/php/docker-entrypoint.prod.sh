#!/bin/sh
set -e

echo "Starting entrypoint script"

# Check if APP_KEY is set
if [ -z "$APP_KEY" ]; then
    echo "Error: APP_KEY is not set. Please configure the APP_KEY in your environment."
    exit 1
fi

echo "Running database migrations"
php artisan migrate:fresh --force || { echo "Database migration failed"; exit 1; }

echo "Running database seeder"
php artisan db:seed --force || { echo "Database seeding failed"; exit 1; }

echo "Caching configuration"
php artisan config:cache || { echo "Config cache failed"; exit 1; }

echo "Caching routes"
php artisan route:cache || { echo "Route cache failed"; exit 1; }

echo "Caching views"
php artisan view:cache || { echo "View cache failed"; exit 1; }

echo "Optimizing application"
php artisan optimize || { echo "Optimization failed"; exit 1; }

# Start PHP-FPM
php-fpm --nodaemonize &

# WebSocketサーバーの起動（backgroundで実行）
if [ "$RUN_WEBSOCKETS" = "true" ]; then
    echo "Starting WebSocket server on port 6001"
    php artisan websockets:serve --host=0.0.0.0 &
fi

echo "Starting Nginx"
exec nginx -g 'daemon off;'
