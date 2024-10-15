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

# シーディングが必要かどうかをチェック（例：admin ユーザーの存在確認）
ADMIN_EXISTS=$(php artisan tinker --execute="echo \App\Models\User::where('username', 'admin')->exists() ? 'true' : 'false';")

if [ "$ADMIN_EXISTS" = "false" ]; then
    echo "Running database seeder"
    php artisan db:seed --force
else
    echo "Skipping database seeder as admin user already exists"
fi

echo "Caching configuration"
php artisan config:cache || { echo "Config cache failed"; exit 1; }

echo "Caching routes"
php artisan route:cache || { echo "Route cache failed"; exit 1; }

echo "Caching views"
php artisan view:cache || { echo "View cache failed"; exit 1; }

echo "Optimizing application"
php artisan optimize || { echo "Optimization failed"; exit 1; }

echo "Entrypoint script completed successfully"

exec "$@"
