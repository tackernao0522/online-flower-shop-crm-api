<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function check()
    {
        try {
            $status = [
                'status' => 'ok',
                'timestamp' => now()->toISOString(),
                'services' => [
                    'app' => 'ok',
                    'database' => 'ok',
                    'websocket' => 'ok'
                ]
            ];

            // データベース接続チェック
            try {
                DB::connection()->getPdo();
            } catch (\Exception $e) {
                Log::error('Health check - Database connection failed: ' . $e->getMessage());
                $status['services']['database'] = 'error';
                $status['status'] = 'error';  // データベースは重要なので全体のステータスを変更
            }

            // WebSocketサーバーチェック（設定が有効な場合のみ）
            if (config('websockets.enabled', false)) {
                try {
                    $connection = @fsockopen('127.0.0.1', 6001, $errno, $errstr, 1);
                    if (!$connection) {
                        $status['services']['websocket'] = 'error';
                        // WebSocketはオプショナルなので全体のstatusは変更しない
                        Log::warning('Health check - WebSocket server is not available');
                    } else {
                        fclose($connection);
                    }
                } catch (\Exception $e) {
                    Log::warning('Health check - WebSocket check failed: ' . $e->getMessage());
                    $status['services']['websocket'] = 'error';
                }
            }

            // キャッシュディレクトリの書き込み権限チェック
            $storagePath = storage_path('framework/cache');
            if (!is_writable($storagePath)) {
                Log::error('Health check - Cache directory is not writable');
                $status['services']['app'] = 'error';
                $status['status'] = 'error';
            }

            // メモリ使用量のチェック
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            if ($memoryUsage > $this->getMemoryLimitInBytes($memoryLimit) * 0.9) {
                Log::warning('Health check - High memory usage: ' . $this->formatBytes($memoryUsage));
                // メモリ使用量は警告としてログに記録するだけ
            }

            $statusCode = $status['status'] === 'ok' ? 200 : 500;
            return response()->json($status, $statusCode)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    private function getMemoryLimitInBytes($memoryLimit)
    {
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int)substr($memoryLimit, 0, -1);

        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int)$memoryLimit;
        }
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}
