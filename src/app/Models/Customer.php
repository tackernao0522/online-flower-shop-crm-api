<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Customer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phoneNumber',
        'address',
        'birthDate',
    ];

    protected $casts = [
        'birthDate' => 'date',
    ];

    /**
     * 検索スコープメソッド
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $term
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function searchByTerm($query, $term)
    {
        // 入力された検索文字列をサニタイズ
        $sanitizedTerm = htmlspecialchars($term, ENT_QUOTES, 'UTF-8');

        // 空白でクエリを分割して複数の単語を処理
        $terms = preg_split('/\s+/', $sanitizedTerm);

        return $query->where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                // 各単語を "名前" または "電話番号" に部分一致させる
                $q->where(function ($subQuery) use ($term) {
                    $subQuery->where('name', 'like', "%{$term}%")
                        ->orWhereRaw("REPLACE(REPLACE(phoneNumber, '-', ''), ' ', '') like ?", ["%{$term}%"]);
                });
            }
        });
    }
}
