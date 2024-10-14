#!/bin/sh
set -e

echo "Starting entrypoint script"

# Check if required environment variables are set
required_vars="APP_KEY DB_USERNAME DB_PASSWORD PUSHER_APP_ID PUSHER_APP_KEY PUSHER_APP_SECRET PUSHER_APP_CLUSTER JWT_SECRET"
for var in $required_vars; do
    if [ -z "$(eval echo \$$var)" ]; then
        echo "Error: $var is not set. Please check your Secrets Manager configuration."
        exit 1
    fi
done

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
