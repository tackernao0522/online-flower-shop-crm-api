<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\HealthCommand::class,
        \App\Console\Commands\CleanupStatsLogs::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // ユーザーのオンラインステータス更新（既存）
        $schedule->command('users:update-online-status')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // 統計ログのクリーンアップ（新規追加）
        $schedule->command('stats:cleanup')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/stats-cleanup.log'))
            ->before(function () {
                Log::info('Starting stats cleanup task');
            })
            ->after(function () {
                Log::info('Stats cleanup task completed');
            })
            ->onFailure(function () {
                Log::error('Stats cleanup task failed');
            })
            ->onSuccess(function () {
                Log::info('Stats cleanup task executed successfully');
            });

        // 開発環境でのログ出力
        if (config('app.env') === 'local') {
            $schedule->command('schedule:list')
                ->daily()
                ->appendOutputTo(storage_path('logs/schedule.log'));
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        // コマンドディレクトリからコマンドを自動で読み込み
        $this->load(__DIR__ . '/Commands');

        // 追加のコンソールルートを読み込み
        require base_path('routes/console.php');
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone()
    {
        return config('app.timezone', 'Asia/Tokyo');
    }

    /**
     * Determine if the given event mutates the application.
     *
     * @param  mixed  $event
     * @return bool
     */
    protected function whenEventShouldRunInMaintenanceMode($event): bool
    {
        return in_array($event->command, [
            'stats:cleanup',
            'users:update-online-status',
        ]);
    }
}
