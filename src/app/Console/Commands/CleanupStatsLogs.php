<?php

namespace App\Console\Commands;

use App\Models\StatsLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStatsLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stats:cleanup {--days=30 : 保持する日数}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '古い統計ログを削除します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $days = $this->option('days');
            $this->info("実行開始: {$days}日以前の統計ログを削除します...");

            $beforeCount = StatsLog::count();
            $deletedCount = StatsLog::where('recorded_at', '<', now()->subDays($days))->delete();
            $afterCount = StatsLog::count();

            $this->info("----------------------------------------");
            $this->info("削除完了:");
            $this->info("- 削除前の総レコード数: {$beforeCount}");
            $this->info("- 削除されたレコード数: {$deletedCount}");
            $this->info("- 削除後の総レコード数: {$afterCount}");
            $this->info("----------------------------------------");

            Log::info('Stats cleanup completed', [
                'days_threshold' => $days,
                'records_deleted' => $deletedCount
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Stats cleanup failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("エラーが発生しました: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
