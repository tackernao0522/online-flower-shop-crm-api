<?php

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
    if (app()->bound('websockets') && app('websockets')->enabled()) {
        return response('OK', 200);
    }
    return response('WebSocket Server Not Available', 503);
});
