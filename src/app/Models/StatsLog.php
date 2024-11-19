<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class StatsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_type',
        'current_value',
        'previous_value',
        'change_rate',
        'recorded_at'
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'change_rate' => 'decimal:2',
        'current_value' => 'integer',
        'previous_value' => 'integer',
    ];

    /**
     * 特定のメトリクスタイプの最新の統計と取得
     */
    public static function getLatestStats(string $metricType): ?self
    {
        return static::where('metric_type', $metricType)
            ->latest('recorded_at')
            ->first();
    }

    /**
     * 特定のメトリクスタイプの統計履歴を取得
     */
    public static function getStatsHistory(
        string $metricType,
        int $limit = 10
    ): Collection {
        return static::where('metric_type', $metricType)
            ->latest('recorded_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 古い統計データを削除
     */
    public static function cleanupOldStats(int $daysToKeep = 30): int
    {
        return static::where('recorded_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }
}
