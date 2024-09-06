<?php

namespace Tests\Unit\Console\Commands;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class UpdateOnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    function オンラインステータスが正しく更新されること()
    {
        // 5分以上前のアクティブユーザーを作成
        $oldActiveUser = User::factory()->create([
            'is_online' => true,
            'last_activity' => Carbon::now()->subMinutes(6)
        ]);

        // 5分以内のアクティブユーザーを作成
        $recentActiveUser = User::factory()->create([
            'is_online' => true,
            'last_activity' => Carbon::now()->subMinutes(4)
        ]);

        // オフラインユーザーを作成
        $offlineUser = User::factory()->create([
            'is_online' => false,
            'last_activity' => Carbon::now()->subMinutes(10)
        ]);

        // コマンドを実行
        Artisan::call('users:update-online-status');

        // データベースを再取得
        $oldActiveUser->refresh();
        $recentActiveUser->refresh();
        $offlineUser->refresh();

        // アサーション
        $this->assertFalse((bool)$oldActiveUser->is_online);
        $this->assertTrue((bool)$recentActiveUser->is_online);
        $this->assertFalse((bool)$offlineUser->is_online);
    }

    /**
     * @test
     */
    function コマンドが正常に実行され成功メッセージが表示されること()
    {
        $this->artisan('users:update-online-status')
            ->expectsOutput('User online statuses updated successfully.')
            ->assertExitCode(0);
    }

    /**
     * @test
     */
    function オンラインユーザーが存在しない場合も正常に実行されること()
    {
        // オフラインユーザーのみを作成
        User::factory()->count(3)->create([
            'is_online' => false,
            'last_activity' => Carbon::now()->subMinutes(10)
        ]);

        $this->artisan('users:update-online-status')
            ->expectsOutput('User online statuses updated successfully.')
            ->assertExitCode(0);
    }
}
