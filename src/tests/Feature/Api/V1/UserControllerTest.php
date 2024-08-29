<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\UserController;
use App\Models\User;
use Gate;
use Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function actingAsAdmin()
    {
        $admin = User::factory()->admin()->create();
        $token = JWTAuth::fromUser($admin);

        // 現在のテスト実行中の認証されたユーザーを設定
        JWTAuth::setToken($token);
        JWTAuth::authenticate();

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function actingAsUser($role = 'STAFF')
    {
        $user = User::factory()->create(['role' => $role]);
        $token = JWTAuth::fromUser($user);

        // 現在のテスト実行中の認証されたユーザーを設定
        JWTAuth::setToken($token);
        JWTAuth::authenticate();

        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * @test
     */
    function 管理者が全ユーザーを閲覧できること()
    {
        User::factory()->count(5)->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'email',
                        'role',
                        'last_login_date',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'meta' => [
                    'currentPage',
                    'totalPages',
                    'totalCount',
                ]
            ]);
    }

    /**
     * @test
     */
    function 管理者が新しいユーザーを作成できること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->postJson('/api/v1/users', [
                'username' => 'newuser',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'STAFF',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'username',
                'email',
                'role',
                'createdAt',
            ]);

        $this->assertDatabaseHas('users', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
        ]);
    }

    /**
     * @test
     */
    function 管理者が特定のユーザー情報を閲覧できること()
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'username',
                'email',
                'role',
                'lastLoginDate',
                'createdAt',
                'updatedAt',
            ]);
    }

    /**
     * @test
     */
    function 管理者がユーザー情報を更新できること()
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->putJson("/api/v1/users/{$user->id}", [
                'email' => 'updated@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'username',
                'email',
                'role',
                'updatedAt'
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
        ]);
    }

    /**
     * @test
     */
    function 管理者がユーザーを削除できること()
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * @test
     */
    function バリデーションエラーでユーザーを作成できないこと()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->postJson('/api/v1/users', [
                'username' => '', // 空の名前
                'email' => 'not-an-email', // 無効なメールアドレス
                'password' => '123', // 短すぎるパスワード
                'role' => 'INVALID_ROLE', // 無効な役割
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'email', 'password', 'role']);
    }

    /**
     * @test
     */
    function 権限のないユーザーがユーザーリストにアクセスできないこと()
    {
        $response = $this->withHeaders($this->actingAsUser())
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    /**
     * @test
     */
    function 存在しないユーザーにアクセスすると404エラーが返されること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users/999999');

        $response->assertStatus(404);
    }

    /**
     * @test
     */
    function ユーザー情報の更新時にバリデーションエラーが発生すること()
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->putJson("/api/v1/users/{$user->id}", [
                'email' => 'not-an-email',
                'role' => 'INVALID_ROLE',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'role']);
    }

    /**
     * @test
     */
    function 管理者以外のユーザーが他のユーザーを削除できないこと()
    {
        $userToDelete = User::factory()->create();

        $response = $this->withHeaders($this->actingAsUser())
            ->deleteJson("/api/v1/users/{$userToDelete->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $userToDelete->id]);
    }

    /**
     * @test
     */
    function ソート機能が正しく動作すること()
    {
        User::factory()->count(5)->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?sort=-username');

        $response->assertStatus(200);
        $users = $response->json('data');
        $this->assertEquals(6, count($users));
        $this->assertTrue($users[0]['username'] > $users[1]['username']);
    }

    /**
     * @test
     */
    function フィルタリング機能が正しく動作すること()
    {
        User::factory()->count(3)->create(['role' => 'STAFF']);
        User::factory()->count(2)->create(['role' => 'MANAGER']);

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?role=STAFF');

        $response->assertStatus(200);
        $users = $response->json('data');
        $this->assertEquals(3, count($users));
        $this->assertTrue(collect($users)->every(fn($user) => $user['role'] === 'STAFF'));
    }

    /**
     * @test
     */
    function ページネーションが正しく動作すること()
    {
        User::factory()->count(25)->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['currentPage', 'totalPages', 'totalCount']
            ]);

        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(3, $response->json('meta.totalPages'));
        $this->assertEquals(26, $response->json('meta.totalCount'));
    }

    /**
     * @test
     */
    function 無効なソートパラメータでエラーが返されること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?sort=invalid_field');

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_PARAMETER',
                    'message' => '無効なソートパラメータです。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 管理者が自身を更新できること()
    {
        $headers = $this->actingAsAdmin();

        $response = $this->withHeaders($headers)
            ->putJson('/api/v1/users/' . JWTAuth::user()->id, [
                'email' => 'newemail@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => 'newemail@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => JWTAuth::user()->id,
            'email' => 'newemail@example.com',
        ]);
    }

    /**
     * @test
     */
    function 無効なロールでフィルタリングするとエラーが返されること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?role=INVALID_ROLE');

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_PARAMETER',
                    'message' => '無効な役割パラメータです。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 無効なリミットパラメータでエラーが返されること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?limit=invalid');

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_PARAMETER',
                    'message' => '無効な limit パラメータです。'
                ]
            ]);
    }

    /**
     * @test
     */
    function リミット範囲外の場合にエラーが返されること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?limit=101');

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_PARAMETER',
                    'message' => '無効な limit パラメータです。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 管理者が存在しないユーザーを更新しようとするとエラーが返されること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->putJson('/api/v1/users/999999', [
                'email' => 'newemail@example.com',
            ]);

        $response->assertStatus(404);
    }

    /**
     * @test
     */
    function 管理者が自身を削除しようとするとエラーが返されること()
    {
        $headers = $this->actingAsAdmin();

        $response = $this->withHeaders($headers)
            ->deleteJson('/api/v1/users/' . JWTAuth::user()->id);

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
    function ユーザー作成時に例外が発生した場合エラーレスポンスが返されること()
    {
        $mockController = \Mockery::mock(UserController::class)->makePartial();
        $mockController->shouldReceive('authorize')->once()->andThrow(new AuthorizationException);
        $this->app->instance(UserController::class, $mockController);

        $response = $this->withHeaders($this->actingAsAdmin())
            ->postJson('/api/v1/users', [
                'username' => 'newuser',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'STAFF',
            ]);

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
    function ユーザー表示時に例外が発生した場合エラーレスポンスが返されること()
    {
        $this->mock('Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('authorize')
            ->andThrow(new AuthorizationException);

        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson("/api/v1/users/{$user->id}");

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
    function ユーザー更新時にパスワードが正しくハッシュ化されること()
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->putJson("/api/v1/users/{$user->id}", [
                'password' => 'newpassword123',
            ]);

        $response->assertStatus(200);

        $updatedUser = User::find($user->id);
        $this->assertTrue(Hash::check('newpassword123', $updatedUser->password));
    }

    /**
     * @test
     */
    function ユーザー更新時に例外が発生した場合はエラーレスポンスが返されること()
    {
        $this->mock('Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('authorize')
            ->andThrow(new AuthorizationException);

        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->putJson("/api/v1/users/{$user->id}", [
                'email' => 'newemail@example.com',
            ]);

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
    function 存在しないユーザーにアクセスするとModelNotFoundExceptionが発生すること()
    {
        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => '指定されたリソースが見つかりません。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 予期せぬ例外が発生した場合にサーバーエラーが返されること()
    {
        Gate::shouldReceive('allows')->andThrow(new \Exception('Unexpected error'));

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users');

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
    function ユーザー作成時に予期せぬ例外が発生した場合サーバーエラーが返されること()
    {
        $mockController = \Mockery::mock(UserController::class)->makePartial();
        $mockController->shouldReceive('store')->andThrow(new \Exception('Unexpected error'));
        $this->app->instance(UserController::class, $mockController);

        $response = $this->withHeaders($this->actingAsAdmin())
            ->postJson('/api/v1/users', [
                'username' => 'newuser',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'STAFF',
            ]);

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
    function 存在しないユーザーの更新を試みた場合NotFoundエラーが返されること()
    {
        $this->app->bind('App\Models\User', function ($app) {
            throw new ModelNotFoundException;
        });

        $response = $this->withHeaders($this->actingAsAdmin())
            ->putJson('/api/v1/users/99999', [
                'email' => 'newemail@example.com',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => '指定されたリソースが見つかりません。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 管理者以外のユーザーがユーザー情報を更新しようとするとエラーが返されること()
    {
        $userToUpdate = User::factory()->create();

        $response = $this->withHeaders($this->actingAsUser())
            ->putJson("/api/v1/users/{$userToUpdate->id}", [
                'email' => 'newemail@example.com',
            ]);

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
    function ユーザー削除時に例外が発生した場合エラーレスポンスが返されること()
    {
        $user = User::factory()->create();

        $userMock = \Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('delete')->andThrow(new \Exception('Unexpected error'));
        $this->app->instance(User::class, $userMock);

        $response = $this->withHeaders($this->actingAsAdmin())
            ->deleteJson("/api/v1/users/{$user->id}");

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
    function ユーザー作成時に特定のエラーコードが返されること()
    {
        $mockController = \Mockery::mock(UserController::class)->makePartial();
        $mockController->shouldReceive('store')->andThrow(new \Exception('Specific error message'));
        $this->app->instance(UserController::class, $mockController);

        $response = $this->withHeaders($this->actingAsAdmin())
            ->postJson('/api/v1/users', [
                'username' => 'newuser',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'STAFF',
            ]);

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
    function モデルが見つからない場合のエラーレスポンスが返されること()
    {
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('resolveRouteBinding')->andThrow(new ModelNotFoundException());
        $this->app->instance(User::class, $userMock);

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => '指定されたリソースが見つかりません。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 権限がない場合のエラーレスポンスが正しく返されること()
    {
        $this->mock('Illuminate\Contracts\Auth\Access\Gate')
            ->shouldReceive('authorize')
            ->andThrow(new AuthorizationException);

        $user = User::factory()->create();

        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson("/api/v1/users/{$user->id}");

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
    function 無効なリクエストパラメータによる例外が発生した場合エラーレスポンスが返されること()
    {
        // 無効なソートパラメータを使ってリクエストを送信
        $response = $this->withHeaders($this->actingAsAdmin())
            ->getJson('/api/v1/users?sort=invalid_field');

        // レスポンスのステータスコードとJSON構造を検証
        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_PARAMETER',
                    'message' => '無効なソートパラメータです。'
                ]
            ]);
    }

    // /**
    //  * @test
    //  */
    // index_メソッドでModelNotFoundExceptionが発生した場合の処理をテスト()
    // {
    //     $mockQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
    //     $mockQuery->shouldReceive('get')->andReturn(collect([]));
    //     $mockQuery->shouldReceive('isEmpty')->andReturn(true);
    //     $mockQuery->shouldReceive('items')->andReturn([]);
    //     $mockQuery->shouldReceive('currentPage')->andReturn(1);
    //     $mockQuery->shouldReceive('lastPage')->andReturn(1);
    //     $mockQuery->shouldReceive('total')->andReturn(0);

    //     $mockUser = Mockery::mock(User::class);
    //     $mockUser->shouldReceive('query')->andReturn($mockQuery);

    //     $this->instance(User::class, $mockUser);

    //     Gate::shouldReceive('authorize')->andReturn(true);

    //     $response = $this->withHeaders($this->actingAsAdmin())
    //         ->getJson('/api/v1/users');

    //     $response->assertStatus(404)
    //         ->assertJson([
    //             'error' => [
    //                 'code' => 'RESOURCE_NOT_FOUND',
    //                 'message' => '指定されたユーザーが見つかりません。'
    //             ]
    //         ]);
    // }

    /**
     * @test
     */
    function store_メソッドで一般的な例外が発生した場合の処理をテスト()
    {
        $mockController = Mockery::mock(UserController::class)->makePartial();
        $mockController->shouldReceive('store')->andThrow(new \Exception('テストエラー'));
        $this->app->instance(UserController::class, $mockController);

        $response = $this->withHeaders($this->actingAsAdmin())
            ->postJson('/api/v1/users', [
                'username' => 'newuser',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'STAFF',
            ]);

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
    function errorResponse_メソッドでModelNotFoundExceptionが適切に処理されることをテスト()
    {
        $controller = new UserController();
        $reflector = new \ReflectionClass($controller);
        $method = $reflector->getMethod('errorResponse');
        $method->setAccessible(true);

        $exception = new ModelNotFoundException('モデルが見つかりません');
        $response = $method->invoke($controller, $exception);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals([
            'error' => [
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => '指定されたユーザーが見つかりません。'
            ]
        ], json_decode($response->getContent(), true));
    }
}
