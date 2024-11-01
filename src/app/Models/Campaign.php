<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
