<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HealthCommand extends Command
{
    protected $signature = 'health';
    protected $description = 'Health check command for ECS';

    public function handle()
    {
        try {
            // データベース接続チェック
            DB::connection()->getPdo();

            // WebSocketサーバーチェック
            if (config('websockets.enabled', false)) {
                $connection = @fsockopen('127.0.0.1', 6001, $errno, $errstr, 1);
                if (!$connection) {
                    // WebSocketエラーはワーニングとして扱う
                    $this->warn('WebSocket server is not available');
                } else {
                    fclose($connection);
                    $this->info('All systems are operational');
                }
            }

            return 0; // 成功
        } catch (\Exception $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            return 1; // 失敗
        }
    }
}
