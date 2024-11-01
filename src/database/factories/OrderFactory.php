<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 注文日時は過去３ヶ月以内でランダム
        $orderDate = $this->faker->dateTimeBetween('-3 months', 'now');

        return [
            'orderNumber' => 'ORD-' . $orderDate->format('Ymd') . '-' . strtoupper($this->faker->bothify('##??')),
            'orderDate' => $orderDate,
            'totalAmount' => 0, // 注文明細作成後に更新
            'status' => $this->faker->randomElement(Order::getAvailableStatuses()),
            'discountApplied' => 0, // キャンペーン適用後に更新
            'customerId' => Customer::factory(),
            'userId' => User::factory()->staff(),
            'campaignId' => $this->faker->boolean(30) ? Campaign::factory() : null, // 30%の確率でキャンペーン適用
        ];
    }

    /**
     * 処理中の注文を設定
     */
    public function processing()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Order::STATUS_PROCESSING,
                'orderDate' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    /**
     * 配送済みの注文を設定
     */
    public function delivered()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Order::STATUS_DELIVERD,
                'orderDate' => $this->faker->dateTimeBetween('-3 months', '-1 week'),
            ];
        });
    }

    /**
     * キャンセルされた注文を設定
     */
    public function cancelled()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Order::STATUS_CANCELLED,
                'orderDate' => $this->faker->dateTimeBetween('-3 months', '-1 week'),
            ];
        });
    }
}
