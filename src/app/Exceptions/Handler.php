<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->logException($e);
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

        if ($exception instanceof ValidationException) {
            return parent::render($request, $exception);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

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

    private function logException(Throwable $exception)
    {
        Log::error($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
