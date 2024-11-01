<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Campaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;  // 追加

class OrderSeeder extends Seeder
{
    private $faker;  // プロパティを追加

    public function __construct()
    {
        $this->faker = Faker::create();  // コンストラクタでFakerインスタンスを生成
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 以下は同じ
        DB::transaction(function () {
            // 進行中の注文を作成
            $this->createOrdersWithStatus('processing', 5);

            // 配送済みの注文を作成
            $this->createOrdersWithStatus('delivered', 10);

            // キャンセルされた注文を作成
            $this->createOrdersWithStatus('cancelled', 3);

            $this->command->info("Total orders created: " . Order::count());
            $this->command->info("Total order items created: " . OrderItem::count());
        });
    }

    private function createOrdersWithStatus(string $status, int $count): void
    {
        Order::factory()
            ->count($count)
            ->$status()
            ->create()
            ->each(function ($order) {
                // Fakerを$this->fakerとして使用
                $orderItems = OrderItem::factory()
                    ->count($this->faker->numberBetween(1, 3))
                    ->create(['orderId' => $order->id]);

                // 以下は同じ
                $subtotal = $orderItems->sum(function ($item) {
                    return $item->quantity * $item->unitPrice;
                });

                $discount = 0;
                if ($order->campaignId) {
                    $campaign = Campaign::find($order->campaignId);
                    if ($campaign) {
                        $discount = floor($subtotal * ($campaign->discountRate / 100));
                    }
                }

                $order->update([
                    'totalAmount' => $subtotal - $discount,
                    'discountApplied' => $discount
                ]);
            });
    }
}
