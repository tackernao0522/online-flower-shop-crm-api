#!/bin/sh
set -e

echo "Starting entrypoint script"

# Generate application key if not set
# php artisan key:generate --force

echo "Running database migrations"
php artisan migrate --force || { echo "Migration failed"; exit 1; }

echo "Caching configuration"
php artisan config:cache || { echo "Config cache failed"; exit 1; }

echo "Caching routes"
php artisan route:cache || { echo "Route cache failed"; exit 1; }

echo "Caching views"
php artisan view:cache || { echo "View cache failed"; exit 1; }

echo "Entrypoint script completed successfully"

exec "$@"
