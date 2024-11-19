<?php

namespace App\Services;

use App\Models\StatsLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StatsService
{
    /**
     * 統計情報を更新
     *
     * @param string $metricType 統計の種類
     * @param int $currentValue 現在の値
     * @param bool $useSmoothing 将来的なデータ平滑化のためのフラグ（現在は未実装）
     * @param int|null $forcePreviousValue 強制的に設定する前回値
     */
    public function updateStats(string $metricType, int $currentValue): array
    {
        $lockKey = "stats_{$metricType}_lock";

        return Cache::lock($lockKey, 10)->block(5, function () use ($metricType, $currentValue) {
            $latestStats = $this->getLatestStatsLog($metricType);

            // 値が同じ場合は更新をスキップ
            if ($latestStats && $latestStats->current_value === $currentValue) {
                Log::info("Stats update skipped - no change", [
                    'metric_type' => $metricType,
                    'value' => $currentValue
                ]);
                return $this->formatStats($latestStats);
            }

            $previousValue = $latestStats ? $latestStats->current_value : $currentValue;
            $changeRate = $this->calculateChangeRate($currentValue, $previousValue);

            $newStats = StatsLog::create([
                'metric_type' => $metricType,
                'current_value' => $currentValue,
                'previous_value' => $previousValue,
                'change_rate' => $changeRate,
                'recorded_at' => now()
            ]);

            Log::info("Stats updated", [
                'metric_type' => $metricType,
                'current_value' => $currentValue,
                'previous_value' => $previousValue,
                'change_rate' => $changeRate
            ]);

            return $this->formatStats($newStats);
        });
    }

    /**
     * 最新の統計情報を取得
     *
     * @param string $metricType
     * @return array
     */
    public function getLatestStats(string $metricType): array
    {
        $stats = $this->getLatestStatsLog($metricType);

        return $stats ? $this->formatStats($stats) : [
            'currentValue' => 0,
            'previousValue' => 0,
            'changeRate' => 0
        ];
    }

    /**
     * 最新の統計ログを取得
     *
     * @param string $metricType
     * @return StatsLog|null
     */
    private function getLatestStatsLog(string $metricType): ?StatsLog
    {
        return StatsLog::where('metric_type', $metricType)
            ->latest('recorded_at')
            ->first();
    }

    /**
     * 初期統計を作成
     *
     * @param string $metricType
     * @param int $currentValue
     * @return StatsLog
     */
    private function createInitialStats(string $metricType, int $currentValue): StatsLog
    {
        return StatsLog::create([
            'metric_type' => $metricType,
            'current_value' => $currentValue,
            'previous_value' => $currentValue,
            'change_rate' => 0,
            'recorded_at' => now()
        ]);
    }

    /**
     * 統計データを配列形式にフォーマット
     *
     * @param StatsLog $stats
     * @return array
     */
    private function formatStats(StatsLog $stats): array
    {
        return [
            'currentValue' => $stats->current_value,
            'previousValue' => $stats->previous_value,
            'changeRate' => $stats->change_rate
        ];
    }

    /**
     * 変動率を計算
     *
     * @param int $current
     * @param int $previous
     * @return float
     */
    public function calculateChangeRate(int $current, int $previous): float
    {
        if ($previous === 0) {
            return 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }
}
