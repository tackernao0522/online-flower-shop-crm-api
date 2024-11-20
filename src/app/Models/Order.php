<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use App\Services\StatsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * 注文ステータスの定数
     */
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_CONFIRMED = 'CONFIRMED';
    const STATUS_SHIPPED = 'SHIPPED';
    const STATUS_DELIVERED = 'DELIVERED';
    const STATUS_CANCELLED = 'CANCELLED';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string, mixed>
     */
    protected $fillable = [
        'orderNumber',
        'orderDate',
        'totalAmount',
        'status',
        'discountApplied',
        'customerId',
        'userId',
        'campaignId',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'orderDate' => 'datetime',
        'totalAmount' => 'integer',
        'discountApplied' => 'integer',
    ];

    /**
     * モデルの「ブート」メソッド
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($order) {
            if ($order->isDirty('status') && (
                $order->status === self::STATUS_CANCELLED ||
                $order->getOriginal('status') === self::STATUS_CANCELLED
            )) {
                $lockKey = 'order_stats_update_lock';

                // ロックを使用して重複更新を防ぐ
                Cache::lock($lockKey, 10)->get(function () use ($order) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 更新前の状態をログに記録
                            Log::info('Updating stats for order:', [
                                'order_id' => $order->id,
                                'previous_status' => $order->getOriginal('status'),
                                'new_status' => $order->status
                            ]);

                            $activeOrderCount = self::getActiveOrderCount();
                            $totalSales = self::getActiveTotalAmount();

                            // 統計更新を実行
                            $orderStats = app(StatsService::class)->updateStats('order_count', $activeOrderCount);
                            $salesStats = app(StatsService::class)->updateStats('sales', $totalSales);

                            // 更新後の状態をログに記録
                            Log::info('Stats updated for order:', [
                                'order_id' => $order->id,
                                'active_order_count' => $activeOrderCount,
                                'total_sales' => $totalSales,
                                'order_stats' => $orderStats,
                                'sales_stats' => $salesStats
                            ]);
                        });
                    } catch (\Exception $e) {
                        Log::error('Failed to update order stats:', [
                            'error' => $e->getMessage(),
                            'order_id' => $order->id,
                            'trace' => $e->getTraceAsString()
                        ]);
                        throw $e;
                    }
                });
            }
        });
    }

    /**
     * 利用可能な注文ステータス配列を取得
     *
     * @return array<string>
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_CONFIRMED,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * アクティブな注文の総数を取得
     *
     * @return int
     */
    public static function getActiveOrderCount(): int
    {
        // キャッシュキーを定義
        $cacheKey = 'active_order_count';

        return Cache::remember($cacheKey, 60, function () {
            // サブクエリを使用して正確な集計を行う
            return DB::transaction(function () {
                $orderItems = DB::table('order_items')
                    ->select('orderId', DB::raw('SUM(quantity) as total_quantity'))
                    ->whereNull('deleted_at')
                    ->groupBy('orderId');

                return static::whereNotIn('status', [self::STATUS_CANCELLED])
                    ->whereNull('deleted_at')
                    ->joinSub($orderItems, 'items', function ($join) {
                        $join->on('orders.id', '=', 'items.orderId');
                    })
                    ->sum('items.total_quantity');
            });
        });
    }

    /**
     * アクティブな注文の合計金額を取得
     *
     * @return int
     */
    public static function getActiveTotalAmount(): int
    {
        return static::whereNotIn('status', [self::STATUS_CANCELLED])
            ->whereNull('orders.deleted_at')  // テーブル名を明示的に指定
            ->sum('totalAmount');
    }

    /**
     * 期間指定での注文統計を取得
     *
     * @param string $startDate
     * @param string $endDate
     * @return array<string, mixed>
     */
    public static function getStatsForPeriod(string $startDate, string $endDate): array
    {
        $orders = static::whereNotIn('status', [self::STATUS_CANCELLED])
            ->whereBetween('orderDate', [$startDate, $endDate]);

        return [
            'count' => $orders->count(),
            'totalAmount' => $orders->sum('totalAmount'),
            'avgAmount' => $orders->avg('totalAmount'),
        ];
    }

    /**
     * 注文を行った顧客を取得
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customerId');
    }

    /**
     * 注文を担当したユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * 適用されたキャンペーンを取得
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaignId');
    }

    /**
     * 注文詳細を取得
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'orderId');
    }

    /**
     * 注文合計を再計算 (割引適用前)
     *
     * @return int
     */
    public function calculateTotal(): int
    {
        return $this->orderItems->sum(function ($item) {
            return $item->quantity * $item->unitPrice;
        });
    }

    /**
     * キャンセル可能かどうかを判定
     *
     * @return bool
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING
        ]) && !$this->deleted_at;
    }
    /**
     * 統計ログを取得
     *
     * @return StatsLog|null
     */
    public static function getOrderStats(): ?StatsLog
    {
        return StatsLog::where('metric_type', 'order_count')
            ->latest('recorded_at')
            ->first();
    }

    /**
     * 日付範囲による絞り込み
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $startDate
     * @param string|null $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('orderDate', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('orderDate', '<=', $endDate);
        }
        return $query;
    }

    /**
     * 金額範囲による絞り込み
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $minAmount
     * @param int|null $maxAmount
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAmountRange($query, $minAmount = null, $maxAmount = null)
    {
        if ($minAmount) {
            $query->where('totalAmount', '>=', $minAmount);
        }
        if ($maxAmount) {
            $query->where('totalAmount', '<=', $maxAmount);
        }
        return $query;
    }

    /**
     * ステータスによる絞り込み
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $status = null)
    {
        if ($status) {
            $query->where('status', $status);
        }
        return $query;
    }

    /**
     * 注文番号による検索
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $orderNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderNumberLike($query, $orderNumber)
    {
        if ($orderNumber) {
            return $query->where('orderNumber', 'like', "%{$orderNumber}%");
        }
        return $query;
    }

    /**
     * アクティブな注文のみを取得
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED]);
    }

    /**
     * 顧客名による検索
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $customerName
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCustomerNameLike($query, $customerName)
    {
        if ($customerName) {
            return $query->whereHas('customer', function ($q) use ($customerName) {
                $q->where('name', 'like', "%{$customerName}%");
            });
        }
        return $query;
    }
}
