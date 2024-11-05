<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'orderId',
        'shippingDate',
        'status',
        'trackingNumber',
        'deliveryNote'
    ];

    protected $casts = [
        'shippingDate' => 'datetime'
    ];

    // 配送ステータスの定数
    const STATUS_PREPARING = 'PREPARING';
    const STATUS_READY = 'READY';
    const STATUS_SHIPPED = 'SHIPPED';
    const STATUS_IN_TRANSIT = 'IN_TRANSIT';
    const STATUS_DELIVERED = 'DELIVERED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_RETURNED = 'RETURNED';

    /**
     * 利用可能な配送ステータス一覧を取得
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PREPARING,
            self::STATUS_READY,
            self::STATUS_SHIPPED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_DELIVERED,
            self::STATUS_FAILED,
            self::STATUS_RETURNED
        ];
    }

    /**
     * 関連する注文を取得
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    /**
     * 配送日による絞り込み
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->where('shippingDate', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('shippingDate', '<=', $endDate);
        }
        return $query;
    }

    /**
     * 配送状態による絞り込み
     */
    public function scopeWithStatus($query, $status = null)
    {
        if ($status) {
            $query->where('status', $status);
        }
        return $query;
    }

    /**
     * 追跡番号による検索
     */
    public function scopeTrackingNumberLike($query, $trackingNumber = null)
    {
        if ($trackingNumber) {
            $query->where('trackingNumber', 'like', '%' . $trackingNumber . '%');
        }
        return $query;
    }

    /**
     * 注文番号による検索
     */
    public function scopeOrderNumberLike($query, $orderNumber = null)
    {
        if ($orderNumber) {
            $query->whereHas('order', function ($q) use ($orderNumber) {
                $q->where('orderNumber', 'like', '%' . $orderNumber . '%');
            });
        }
        return $query;
    }


    /**
     * 顧客名による検索
     */
    public function scopeCustomerNameLike($query, $customerName = null)
    {
        if ($customerName) {
            $query->whereHas('order.customer', function ($q) use ($customerName) {
                $q->where('name', 'like', '%' . $customerName . '%');
            });
        }
        return $query;
    }
}
