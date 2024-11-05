<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Campaign extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'startDate',
        'endDate',
        'discountRate',
        'discountCode',
        'description',
        'is_active',
    ];

    protected $casts = [
        'startDate' => 'date',
        'endDate' => 'date',
        'discountRate' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * キャンペーンに関連する注文を取得
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'campaignId');
    }

    /**
     * キャンペーンが現在有効かどうかを判定
     */
    public function isValid(): bool
    {
        $today = now()->startOfDay();
        return $this->is_active &&
            $this->startDate <= $today &&
            $this->endDate >= $today;
    }

    /**
     * 日付範囲による絞り込み
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            // 開始日の条件を修正: キャンペーンの終了日が検索開始日以降
            $query->where('endDate', '>=', $startDate);
        }
        if ($endDate) {
            // 終了日の条件を修正: キャンペーンの開始日が検索終了日以前
            $query->where('startDate', '<=', $endDate);
        }
        return $query;
    }

    /**
     * キャンペーン名による検索
     */
    public function scopeNameLike($query, $name = null)
    {
        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }
        return $query;
    }

    /**
     * 割引コードによる検索
     */
    public function scopeDiscountCode($query, $discountCode = null)
    {
        if ($discountCode) {
            $query->where('discountCode', $discountCode);
        }
        return $query;
    }

    /**
     * 割引率による絞り込み
     */
    public function scopeDiscountRateRange($query, $minRate = null, $maxRate = null)
    {
        if ($minRate) {
            $query->where('discountRate', '>=', $minRate);
        }
        if ($maxRate) {
            $query->where('discountRate', '<=', $maxRate);
        }
        return $query;
    }

    /**
     * アクティブ状態による絞り込み
     */
    public function scopeActive($query, $isActive = null)
    {
        if (!is_null($isActive)) {
            $query->where('is_active', $isActive);
        }
        return $query;
    }

    /**
     * 現在有効なキャンペーンの絞り込み
     */
    public function scopeCurrentlyValid($query)
    {
        $today = now()->startOfDay();
        Log::info('Executing currentlyValid scope:', [
            'today' => $today->toDateString(),
            'conditions' => [
                'startDate_lte' => $today->toDateString(),
                'endDate_gte' => $today->toDateString(),
                'is_active' => true
            ]
        ]);
        return $query->where('startDate', '<=', $today)
            ->where('endDate', '>=', $today)
            ->where('is_active', true);
    }
}
