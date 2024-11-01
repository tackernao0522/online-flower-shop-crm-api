<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'orderId',
        'productId',
        'quantity',
        'unitPrice',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unitPrice' => 'integer',
    ];

    /**
     * 関連する注文を取得
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    /**
     * 関連する商品を取得
     */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    /**
     * 小計を計算
     */
    public function calculateSubtotal(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
