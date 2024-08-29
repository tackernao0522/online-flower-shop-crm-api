<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

class HandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用のルートを設定して、各例外をシミュレートします
        Route::get('/test-authorization', function () {
            throw new AuthorizationException('このアクションを実行する権限がありません。');
        });

        Route::get('/test-model-not-found', function () {
            throw new ModelNotFoundException('指定されたリソースが見つかりません。');
        });

        Route::get('/test-validation', function () {
            throw ValidationException::withMessages(['field' => ['Validation error message']]);
        });

        Route::get('/test-authentication', function () {
            throw new AuthenticationException('Unauthenticated.');
        });

        Route::get('/test-exception', function () {
            throw new \Exception('サーバーエラー');
        });

        Route::get('/test-default', function () {
            throw new \Error('Unhandled error');
        });
    }

    /**
     * @test
     */
    function 権限例外が発生すると403エラーが返されること()
    {
        $response = $this->getJson('/test-authorization');
        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'このアクションを実行する権限がありません。'
                ]
            ]);
    }

    /**
     * @test
     */
    function モデルが見つからない例外が発生すると404エラーが返されること()
    {
        $response = $this->getJson('/test-model-not-found');

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => '指定されたリソースが見つかりません。'
                ]
            ]);
    }

    /** @test */
    function バリデーション例外が発生すると422エラーが返されること()
    {
        $response = $this->getJson('/test-validation');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['field']);
    }

    /**
     * @test
     */
    function 認証例外が発生すると401エラーが返されること()
    {
        $response = $this->getJson('/test-authentication');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /**
     * @test
     */
    function 一般的な例外が発生すると500エラーが返されること()
    {
        $response = $this->getJson('/test-exception');

        $response->assertStatus(500)
            ->assertJson([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'ユーザー操作に失敗しました。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 未処理の例外が発生するとデフォルトの500エラーが返されること()
    {
        $response = $this->getJson('/test-default');

        $response->assertStatus(500);
    }
}
