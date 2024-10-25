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

// ヘルスチェック用のエンドポイント
Route::get('/health', function () {
    // WebSocketサーバーの状態も確認する
    try {
        $connection = @fsockopen('127.0.0.1', 6001, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return response('OK', 200);
        }
    } catch (\Exception $e) {
        Log::error('Health check failed: ' . $e->getMessage());
    }

    // WebSocketサーバーが利用できなくても、アプリケーション自体は正常として扱う
    return response('OK', 200);
});
