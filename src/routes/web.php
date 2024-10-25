<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// デフォルトのヘルスチェック
Route::get('/health', function () {
    return response('OK', 200);
});

// WebSocketサーバー用のヘルスチェック
Route::get('/ws-health', function () {
    $websocketsEnabled = config('websockets.enabled', false);
    $websocketsPort = config('websockets.port', 6001);

    if ($websocketsEnabled) {
        try {
            // ローカルWebSocketサーバーへの接続を確認
            $connection = @fsockopen('127.0.0.1', $websocketsPort);
            if ($connection) {
                fclose($connection);
                return response('OK', 200);
            }
        } catch (\Exception $e) {
            Log::error('WebSocket health check failed: ' . $e->getMessage());
        }
    }

    return response('WebSocket Server Not Available', 503);
});
