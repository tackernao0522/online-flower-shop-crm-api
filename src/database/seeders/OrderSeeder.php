<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Campaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Cache;
use App\Events\OrderCountUpdated;

class OrderSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // キャッシュをクリア
        Cache::forget('previous_order_count');
        Cache::forget('order_change_rate');

        DB::transaction(function () {
            // 進行中の注文を作成
            $this->createOrdersWithStatus('processing', 5);

            // 配送済みの注文を作成
            $this->createOrdersWithStatus('delivered', 10);

            // キャンセルされた注文を作成
            $this->createOrdersWithStatus('cancelled', 3);

            // 有効な注文の総数を取得（CANCELLEDを除外）
            $totalCount = Order::whereNotIn('status', ['CANCELLED'])->count();

            // 初期値を設定
            Cache::put('previous_order_count', $totalCount, now()->addDay());
            Cache::put('order_change_rate', 0, now()->addDay());

            // イベントを発火
            event(new OrderCountUpdated(
                $totalCount,
                $totalCount,
                0
            ));

            $this->command->info("Total orders created: " . Order::count());
            $this->command->info("Total active orders: " . $totalCount);
            $this->command->info("Total order items created: " . OrderItem::count());
            $this->command->info("Initial change rate set to 0%");
        });
    }

    private function createOrdersWithStatus(string $status, int $count): void
    {
        Order::factory()
            ->count($count)
            ->$status()
            ->create()
            ->each(function ($order) {
                $orderItems = OrderItem::factory()
                    ->count($this->faker->numberBetween(1, 3))
                    ->create(['orderId' => $order->id]);

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
