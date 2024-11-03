<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shippingDate = $this->faker->dateTimeBetween('-1 month', '+1 week');

        return [
            'orderId' => function () {
                return Order::inRandomOrder()->first()->id;
            },
            'shippingDate' => $shippingDate,
            'status' => $this->faker->randomElement(Shipment::getAvailableStatuses()),
            'trackingNumber' => $this->faker->boolean(80) ? $this->faker->numerify('##########') : null,
            'deliveryNote' => $this->faker->boolean(30) ? $this->faker->sentence : null,
        ];
    }

    /**
     * 準備中の配送を設定
     */
    public function preparing()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Shipment::STATUS_PREPARING,
                'shippingDate' => $this->faker->dateTimeBetween('now', '+1 week'),
            ];
        });
    }

    /**
     * 発送済みの配送を設定
     */
    public function shipped()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Shipment::STATUS_SHIPPED,
                'shippingDate' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'trackingNumber' => $this->faker->numerify('##########'),
            ];
        });
    }

    /**
     * 配達完了の配送を設定
     */
    public function delivered()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Shipment::STATUS_DELIVERED,
                'shippingDate' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
                'trackingNumber' => $this->faker->numerify('##########'),
            ];
        });
    }
}
