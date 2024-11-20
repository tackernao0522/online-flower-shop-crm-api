<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Campaign;
use App\Models\StatsLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\OrderCountUpdated;
use App\Events\SalesUpdated;

class OrderSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    public function run(): void
    {
        try {
            DB::transaction(function () {
                // シーディング前の統計情報をクリア
                StatsLog::where('metric_type', 'order_count')->delete();
                StatsLog::where('metric_type', 'sales')->delete();

                // 進行中の注文を作成
                $this->createOrdersWithStatus('processing', 100);

                // 配送済みの注文を作成
                $this->createOrdersWithStatus('delivered', 10);

                // キャンセルされた注文を作成
                $this->createOrdersWithStatus('cancelled', 3);

                // 商品総数を計算
                $totalQuantity = OrderItem::whereHas('order', function ($query) {
                    $query->whereNotIn('status', ['CANCELLED'])
                        ->whereNull('deleted_at');
                })->sum('quantity');

                // 売上合計を計算
                $totalSales = Order::whereNotIn('status', ['CANCELLED'])
                    ->whereNull('deleted_at')
                    ->sum('totalAmount');

                // アクティブな注文数を取得（ログ表示用）
                $activeOrders = Order::whereNotIn('status', ['CANCELLED'])
                    ->whereNull('deleted_at')
                    ->count();

                try {
                    // 注文数の統計を記録（注文商品の総数を使用）
                    StatsLog::create([
                        'metric_type' => 'order_count',
                        'current_value' => $totalQuantity,
                        'previous_value' => $totalQuantity,
                        'change_rate' => 0,
                        'recorded_at' => now()
                    ]);

                    // 売上の統計を記録
                    StatsLog::create([
                        'metric_type' => 'sales',
                        'current_value' => $totalSales,
                        'previous_value' => $totalSales,
                        'change_rate' => 0,
                        'recorded_at' => now()
                    ]);

                    // イベント発火（変化率0%で初期化）
                    event(new OrderCountUpdated(
                        $totalQuantity,
                        $totalQuantity,
                        0
                    ));

                    event(new SalesUpdated(
                        $totalSales,
                        $totalSales,
                        0
                    ));

                    // 実行結果をログに出力
                    $this->logSeederResults($activeOrders, $totalQuantity, $totalSales);
                } catch (\Exception $e) {
                    Log::error("Failed to update stats during seeding: " . $e->getMessage());
                    throw $e;
                }
            });
        } catch (\Exception $e) {
            Log::error("Order seeding failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function createOrdersWithStatus(string $status, int $count): void
    {
        Order::factory()
            ->count($count)
            ->$status()
            ->create()
            ->each(function ($order) {
                // 注文明細の作成
                $orderItems = OrderItem::factory()
                    ->count($this->faker->numberBetween(1, 3))
                    ->create(['orderId' => $order->id]);

                // 小計の計算
                $subtotal = $orderItems->sum(function ($item) {
                    return $item->quantity * $item->unitPrice;
                });

                // 割引の計算
                $discount = $this->calculateDiscount($order, $subtotal);

                // 注文合計の更新
                $order->update([
                    'totalAmount' => $subtotal - $discount,
                    'discountApplied' => $discount
                ]);
            });
    }

    private function calculateDiscount(Order $order, float $subtotal): float
    {
        if (!$order->campaignId) {
            return 0;
        }

        $campaign = Campaign::find($order->campaignId);
        if (!$campaign) {
            return 0;
        }

        return floor($subtotal * ($campaign->discountRate / 100));
    }

    private function logSeederResults(int $activeOrders, int $totalQuantity, float $totalSales): void
    {
        $this->command->info("注文データの作成完了");
        $this->command->info("----------------------------------------");
        $this->command->info("総注文数: " . Order::count());
        $this->command->info("有効な注文数: " . $activeOrders);
        $this->command->info("有効な注文の商品総数: " . $totalQuantity);
        $this->command->info("総売上金額: ¥" . number_format($totalSales));
        $this->command->info("注文明細数: " . OrderItem::count());
        $this->command->info("統計データ初期化: ");
        $this->command->info("  - 注文数変動率: 0%");
        $this->command->info("  - 売上変動率: 0%");
        $this->command->info("----------------------------------------");
    }
}
