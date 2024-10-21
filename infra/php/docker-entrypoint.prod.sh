#!/bin/sh
set -e

echo "Starting entrypoint script"

# Check if APP_KEY is set
if [ -z "$APP_KEY" ]; then
    echo "Error: APP_KEY is not set. Please configure the APP_KEY in your environment."
    exit 1
fi

echo "Running database migrations"
php artisan migrate --force

echo "Running database seeder"
php artisan db:seed --force

echo "Caching configuration"
php artisan config:cache || { echo "Config cache failed"; exit 1; }

echo "Caching routes"
php artisan route:cache || { echo "Route cache failed"; exit 1; }

echo "Caching views"
php artisan view:cache || { echo "View cache failed"; exit 1; }

echo "Optimizing application"
php artisan optimize || { echo "Optimization failed"; exit 1; }

# WebSocketサーバーの起動
if [ "$RUN_WEBSOCKETS" = "true" ]; then
  echo "Starting WebSocket server on port 6001"
  php artisan websockets:serve &
fi

echo "Entrypoint script completed successfully"

exec "$@"
