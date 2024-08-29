<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'このアクションを実行する権限がありません。'
                ]
            ], 403);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => '指定されたリソースが見つかりません。'
                ]
            ], 404);
        }

        // バリデーションエラーの処理を追加
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            return parent::render($request, $exception);
        }

        // 認証エラーの処理を追加
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // その他の例外
        if ($exception instanceof \Exception) {
            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'ユーザー操作に失敗しました。'
                ]
            ], 500);
        }

        return parent::render($request, $exception);
    }
}
