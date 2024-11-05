<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

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

    protected $casts = [
        'orderDate' => 'datetime',
        'totalAmount' => 'integer',
        'discountApplied' => 'integer',
    ];

    // 注文ステータスの定数
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_CONFIRMED = 'CONFIRMED';
    const STATUS_SHIPPED = 'SHIPPED';
    const STATUS_DELIVERED = 'DELIVERED';
    const STATUS_CANCELLED = 'CANCELLED';

    /**
     * 利用可能な注文ステータス
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
     */
    public function calculateTotal(): int
    {
        return $this->orderItems->sum(function ($item) {
            return $item->quantity * $item->unitPrice;
        });
    }

    /**
     * キャンセル可能かどうかを判定
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ]);
    }

    /**
     * 日付範囲内でによる絞り込み
     */
    // Order.php
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
     */
    public function scopeWithStatus($query, $status = null)
    {
        if ($status) {
            $query->where('status', $status);
        }
        return $query;
    }

    public function scopeOrderNumberLike($query, $orderNumber)
    {
        if ($orderNumber) {
            return $query->where('orderNumber', 'like', "%{$orderNumber}%");
        }
        return $query;
    }
}
