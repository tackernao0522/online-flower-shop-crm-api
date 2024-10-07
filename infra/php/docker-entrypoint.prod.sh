#!/bin/sh
set -e

# Generate application key if not set
php artisan key:generate --force

# Run database migrations
php artisan migrate --force

# Cache configuration and routes
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
