<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HealthCommand extends Command
{
    protected $signature = 'health';
    protected $description = 'Health check command for ECS';

    public function handle()
    {
        // キャッシュをクリア
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');

        // WebSocket設定の確認
        $websocketsEnabled = config('websockets.enabled', false);
        $websocketsPort = config('websockets.port', 6001);

        if ($websocketsEnabled) {
            try {
                // ローカルWebSocketサーバーへの接続を確認
                $connection = @fsockopen('127.0.0.1', $websocketsPort);
                if ($connection) {
                    fclose($connection);
                    $this->info('WebSocket server is running');
                    return 0;
                }
            } catch (\Exception $e) {
                $this->error('WebSocket server connection failed: ' . $e->getMessage());
            }
        }

        $this->error('WebSocket server is not available');
        return 1;
    }
}
