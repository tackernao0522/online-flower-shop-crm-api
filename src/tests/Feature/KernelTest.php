<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class KernelTest extends TestCase
{
    /** @test */
    public function コマンドがスケジュールされていること()
    {
        // TestKernelのインスタンスを作成
        $events = $this->app->make(Dispatcher::class);
        $kernel = new TestKernel($this->app, $events);

        // スケジュールされたコマンドを確認
        $schedule = app(Schedule::class);
        $kernel->callSchedule($schedule);

        $events = collect($schedule->events());

        // スケジュールされたコマンドが存在することを確認します（デフォルトではempty）
        $this->assertTrue($events->isEmpty(), 'スケジュールされたコマンドが存在しますが、テストには記載されていません。');
    }

    /** @test */
    public function コマンドが正しく登録されていること()
    {
        // TestKernelのインスタンスを作成
        $events = $this->app->make(Dispatcher::class);
        $kernel = new TestKernel($this->app, $events);

        // commands メソッドを呼び出して、コマンドが正しくロードされることをテストします
        $kernel->callCommands();

        // コマンドがロードされたか確認
        $commands = Artisan::all();

        // "inspire" コマンドが登録されていることを確認します
        $this->assertArrayHasKey('inspire', $commands, 'inspire コマンドが登録されていません。');
    }
}
