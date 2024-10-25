<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HealthCommand extends Command
{
    protected $signature = 'health';
    protected $description = 'Health check command for ECS';

    public function handle()
    {
        // WebSocketサーバーが有効化されているか確認
        if (config('websockets.enabled')) {
            $this->info('WebSocket server is enabled');
            return 0;  // 成功を示す
        }

        $this->error('WebSocket server is not enabled');
        return 1;  // 失敗を示す
    }
}
