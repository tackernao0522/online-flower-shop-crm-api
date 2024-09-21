#!/bin/sh
set -e

# Laravel関連のセットアップコマンドを実行
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ポート6001がすでに使用されている場合に強制的に停止する
if netstat -tuln | grep ':6001' > /dev/null 2>&1; then
  echo "Stopping existing WebSocket server on port 6001"
  fuser -k 6001/tcp
fi

# 環境変数CHECKでWebSocketサーバーの起動を制御
if [ "$RUN_WEBSOCKETS" = "true" ]; then
  php artisan websockets:serve &
fi

# コンテナのメインプロセスを実行（例：php-fpm）
exec docker-php-entrypoint "$@"
