<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    function ユーザー管理の全体フローが正常に動作すること()
    {
        // 管理者ユーザーを作成
        $password = 'admin-password';
        $admin = User::factory()->admin()->create([
            'password' => Hash::make($password)
        ]);

        // ログイン
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => $password,
        ]);
        $response->assertStatus(200);

        $token = $response->json('accessToken');

        // 新しいユーザーを作成
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson('/api/v1/users', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'STAFF',
        ]);
        $response->assertStatus(201);
        $userId = $response->json('id');

        // ユーザー情報を取得
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->getJson("/api/v1/users/{$userId}");
        $response->assertStatus(200);

        // ユーザー情報を更新
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->putJson("/api/v1/users/{$userId}", [
            'email' => 'updated@example.com',
        ]);
        $response->assertStatus(200);

        // ユーザーを削除
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->deleteJson("/api/v1/users/{$userId}");
        $response->assertStatus(204);

        // ログアウト
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->postJson('/api/v1/auth/logout');
        $response->assertStatus(200);
    }
}
