<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Password;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Mockery;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // テスト用のセットアップが必要であればここに追加
    }

    protected function tearDown(): void
    {
        Mockery::close(); // Mockeryをクリーンアップ
        parent::tearDown();
    }

    private function authenticateUser($user)
    {
        $token = auth('api')->login($user);
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    /**
     * @test
     */
    function ユーザーがログインできること()
    {
        $password = 'i-love-laravel';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'accessToken',
                'tokenType',
                'expiresIn',
            ]);
    }

    /**
     * @test
     */
    function ユーザーが無効な認証情報でログインできないこと()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'ユーザー名またはパスワードが正しくありません。'
                ]
            ]);
    }

    /**
     * @test
     */
    function ユーザーがパスワードリセットをリクエストできること()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['status']);
    }

    /**
     * @test
     */
    function ユーザーがパスワードを変更できること()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $headers = $this->authenticateUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'old-password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'パスワードが正常に変更されました。'
            ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-password'
        ]);

        $loginResponse->assertStatus(200);
    }

    /**
     * @test
     */
    function ユーザーが新規登録できること()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'STAFF',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'accessToken',
                'tokenType',
                'expiresIn',
            ]);

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * @test
     */
    function ユーザーがログアウトできること()
    {
        $user = User::factory()->create();
        $headers = $this->authenticateUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'ログアウトしました。オンラインステータスを更新しました。'
            ]);

        $this->assertGuest('api');
    }

    /**
     * @test
     */
    function ユーザーがトークンをリフレッシュできること()
    {
        $user = User::factory()->create();
        $headers = $this->authenticateUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'accessToken',
                'tokenType',
                'expiresIn'
            ]);
    }

    /**
     * @test
     */
    function 無効なトークンでリフレッシュできないこと()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json'
        ])->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /**
     * @test
     */
    function 無効なメールアドレスでパスワードをリクエストできないこと()
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['email']);
    }

    /**
     * @test
     */
    function パスワードリセットが成功すること()
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-password'
        ]);

        $loginResponse->assertStatus(200);
    }

    /**
     * @test
     */
    function 無効なトークンでパスワードリセットができないこと()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    /**
     * @test
     */
    function 現在のパスワードが間違っている場合にパスワード変更ができないこと()
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $headers = $this->authenticateUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrong-password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => '現在のパスワードが正しくありません。'
                ]
            ]);
    }

    /**
     * @test
     */
    function 新しいパスワードと確認用パスワードが一致しない場合にパスワード変更ができないこと()
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $headers = $this->authenticateUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'current-password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'different-password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * @test
     */
    function 無効な役割で新規登録できないこと()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'INVALID_ROLE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * @test
     */
    function ログイン時に最終ログイン日時が更新されること()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'last_login_date' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->last_login_date);
        $this->assertEqualsWithDelta(now(), $user->last_login_date, 1);
    }

    /**
     * @test
     */
    function パスワードリセットリンクの送信に失敗した場合エラーが返されること()
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['email']);
    }

    /**
     * @test
     */
    function 新しいパスワードが現在のパスワードと同じ場合にエラーが返されること()
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $headers = $this->authenticateUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'current-password',
                'new_password' => 'current-password',
                'new_password_confirmation' => 'current-password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * @test
     */
    function 新しいパスワードが必要な長さに満たない場合にエラーが返されること()
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $headers = $this->authenticateUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('api/v1/auth/change-password', [
                'current_password' => 'current-password',
                'new_password' => 'short',
                'new_password_confirmation' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * @test
     */
    function 連続ログイン時に最終ログイン日時が更新されること()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'last_login_date' => null,
        ]);

        // 1回目のログイン
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $user->refresh();
        $firstLoginDate = $user->last_login_date;

        // 少し待機
        $this->travel(5)->minutes();

        // 2回目のログイン
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $user->refresh();
        $secondLoginDate = Carbon::parse($user->last_login_date);

        $this->assertNotEquals($firstLoginDate, $secondLoginDate);
        $this->assertTrue($secondLoginDate->gt($firstLoginDate));
    }

    /**
     * @test
     */
    function ログイン失敗時に最終ログイン日時が更新されないこと()
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
            'last_login_date' => null,
        ]);

        // 誤ったパスワードでログイン試行
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $user->refresh();
        $this->assertNull($user->last_login_date);

        // 正しいパスワードでログイン
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_date);
    }

    /**
     * @test
     */
    function リフレッシュ時に例外が発生した場合エラーレスポンスが返されること()
    {
        $user = User::factory()->create();

        $guardMock = Mockery::mock('PHPOpenSourceSaver\JWTAuth\JWTGuard');
        $guardMock->shouldReceive('refresh')->andThrow(new JWTException('Token has expired'));
        $guardMock->shouldReceive('check')->andReturn(true);
        $guardMock->shouldReceive('user')->andReturn($user);

        $authMock = Mockery::mock('Illuminate\Auth\AuthManager');
        $authMock->shouldReceive('guard')->with('api')->andReturn($guardMock);

        $this->app->instance('auth', $authMock);

        $controller = new AuthController();
        $response = $controller->refresh();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(['error' => 'Unauthorized'], $response->getData(true));
    }
}
