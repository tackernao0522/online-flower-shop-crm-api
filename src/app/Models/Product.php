<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stockQuantity',
        'category',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'stockQuantity' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if ($product->stockQuantity < 0) {
                throw new \InvalidArgumentException('在庫数は0以上である必要があります。');
            }
            if ($product->price < 0) {
                throw new \InvalidArgumentException('価格は0以上である必要があります。');
            }
        });
    }

    /**
     * カテゴリーによる絞り込み
     */
    public function scopeWithCategory($query, $category = null)
    {
        if ($category) {
            $query->where('category', $category);
        }
        return $query;
    }

    /**
     * アクティブな商品のみ取得
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 在庫がある商品のみ取得
     */
    public function scopeInStock($query)
    {
        return $query->where('stockQuantity', '>', 0);
    }
}
