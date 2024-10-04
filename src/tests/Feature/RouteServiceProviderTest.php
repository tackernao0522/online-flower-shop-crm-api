<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class RouteServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API routes are properly configured.
     *
     * @return void
     */
    public function testApiRoutesAreConfigured()
    {
        // テスト用ユーザーを作成
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'), // パスワードをハッシュ化して保存
        ]);

        // POSTリクエストを送信して、APIルートが正しく機能しているか確認
        $response = $this->post('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password', // 正しいパスワード
        ]);

        // レスポンスが成功し、ステータスコードが200であることを確認
        $response->assertStatus(200);

        // レスポンスにアクセストークンが含まれているか確認
        $response->assertJsonStructure([
            'accessToken',
            'tokenType',
            'expiresIn',
            'user' => [
                'id',
                'username',
                'email',
                'role',
            ],
        ]);
    }

    /**
     * Test Web routes are properly configured.
     *
     * @return void
     */
    public function testWebRoutesAreConfigured()
    {
        $response = $this->get('/'); // Webルートをテスト

        $response->assertStatus(200); // ステータスが200であることを確認
    }
}
