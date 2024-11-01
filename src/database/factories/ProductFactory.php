<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 商品カテゴリーの配列
        $categories = [
            '花束',
            'アレンジメント',
            '鉢植え',
            'ブライダルブーケ',
            'リース',
            '観葉植物',
        ];

        // 価格帯の設定(1,000円〜50,000円)
        $basePrice = $this->faker->numberBetween(1000, 50000);
        // 100円単位に調整(切り捨て)
        $price = floor($basePrice / 100) * 100;

        return [
            'name' => $this->faker->realText(50),
            'description' => $this->faker->realText(200),
            'price' => $price,
            'stockQuantity' => $this->faker->numberBetween(0, 100),
            'category' => $this->faker->randomElement($categories),
            'is_active' => $this->faker->boolean(90), // 90%に確率でtrueを返す
        ];
    }

    /**
     * 在庫切れ状態の商品を設定
     */
    public function outOfStock()
    {
        return $this->state(function (array $attributes) {
            return [
                'stockQuantity' => 0,
            ];
        });
    }

    /**
     * 非アクティブ状態の商品を設定
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * 高額商品を設定
     */
    public function premium()
    {
        return $this->state(function (array $attributes) {
            $basePrice = $this->faker->numberBetween(50000, 100000);
            return [
                'price' => floor($basePrice / 100) * 100,
            ];
        });
    }
}
