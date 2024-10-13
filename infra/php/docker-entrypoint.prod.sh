#!/bin/sh
set -e

echo "Starting entrypoint script"

# 必須なシークレットが全て設定されているか確認
REQUIRED_SECRETS=("APP_KEY" "DB_USERNAME" "DB_PASSWORD" "JWT_SECRET" "PUSHER_APP_KEY" "PUSHER_APP_SECRET")
for SECRET in "${REQUIRED_SECRETS[@]}"; do
  if [ -z "${!SECRET}" ]; then
    echo "Error: $SECRET is not set. Please check your environment or AWS Secrets Manager configuration."
    exit 1
  fi
done

echo "All required secrets are set."

# データベースのマイグレーションを実行
echo "Running database migrations"
php artisan migrate --force

# シーディングが必要かどうかをチェック（例：adminユーザーの存在確認）
ADMIN_EXISTS=$(php artisan tinker --execute="echo \App\Models\User::where('username', 'admin')->exists() ? 'true' : 'false';")
if [ "$ADMIN_EXISTS" = "false" ]; then
    echo "Running database seeder"
    php artisan db:seed --force
else
    echo "Skipping database seeder as admin user already exists"
fi

# キャッシュの生成と最適化
echo "Caching configuration"
php artisan config:cache || { echo "Config cache failed"; exit 1; }

echo "Caching routes"
php artisan route:cache || { echo "Route cache failed"; exit 1; }

echo "Caching views"
php artisan view:cache || { echo "View cache failed"; exit 1; }

echo "Optimizing application"
php artisan optimize || { echo "Optimization failed"; exit 1; }

echo "Entrypoint script completed successfully"

# コンテナをフォアグラウンドで実行
exec "$@"
