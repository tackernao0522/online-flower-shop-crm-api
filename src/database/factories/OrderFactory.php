<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Customer;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        // 注文日時は過去3ヶ月以内でランダム
        $orderDate = $this->faker->dateTimeBetween('-3 months', 'now');

        // 注文番号の一意性を確保
        do {
            $orderNumber = 'ORD-' . $orderDate->format('Ymd') . '-' .
                strtoupper($this->faker->bothify('##??'));
            $exists = \App\Models\Order::where('orderNumber', $orderNumber)->exists();
        } while ($exists);

        return [
            'orderNumber' => $orderNumber,
            'orderDate' => $orderDate,
            'totalAmount' => 0,
            'status' => $this->faker->randomElement(Order::getAvailableStatuses()),
            'discountApplied' => 0,
            'customerId' => function () {
                return Customer::inRandomOrder()->first()->id;
            },
            'userId' => function () {
                return User::where('role', 'STAFF')->inRandomOrder()->first()->id;
            },
            'campaignId' => function () {
                return $this->faker->boolean(30) ? Campaign::inRandomOrder()->first()->id : null;
            },
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
                'status' => Order::STATUS_DELIVERED,
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
