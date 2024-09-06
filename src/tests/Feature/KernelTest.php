<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class KernelTest extends TestCase
{
    /**
     * @test
     */
    function コマンドがスケジュールされていること()
    {
        // TestKernelのインスタンスを作成
        $events = $this->app->make(Dispatcher::class);
        $kernel = new TestKernel($this->app, $events);

        // スケジュールされたコマンドを確認
        $schedule = app(Schedule::class);
        $kernel->callSchedule($schedule);

        $events = collect($schedule->events());

        // スケジュールされたコマンドが存在することを確認します
        $this->assertFalse($events->isEmpty(), 'スケジュールされたコマンドが存在しません。');

        // 'users:update-online-status' コマンドが毎分スケジュールされていることを確認
        $this->assertTrue($events->contains(function ($event) {
            return strpos($event->command, 'users:update-online-status') !== false && $event->expression === '* * * * *';
        }), 'users:update-online-status コマンドが毎分スケジュールされていません。');
    }

    /**
     * @test
     */
    function コマンドが正しく登録されていること()
    {
        // TestKernelのインスタンスを作成
        $events = $this->app->make(Dispatcher::class);
        $kernel = new TestKernel($this->app, $events);

        // commands メソッドを呼び出して、コマンドが正しくロードされることをテストします
        $kernel->callCommands();

        // コマンドがロードされたか確認
        $commands = Artisan::all();

        // "users:update-online-status" コマンドが登録されていることを確認します
        $this->assertArrayHasKey('users:update-online-status', $commands, 'users:update-online-status コマンドが登録されていません。');
    }
}
