<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use App\Events\CustomerCountUpdated;
use Illuminate\Support\Facades\Event;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // イベントを無効化（コメントを外す）
        Event::fake();

        $count = 3;
        $chunkSize = 10;

        $totalCount = 0;
        $previousTotalCount = 0;

        try {
            for ($i = 0; $i < $count; $i += $chunkSize) {
                $previousTotalCount = $totalCount;
                $customers = Customer::factory()->count(min($chunkSize, $count - $i))->make();

                // 既存の顧客データがない場合のみ挿入
                foreach ($customers as $customer) {
                    $exists = Customer::where('email', $customer->email)->exists();
                    if (!$exists) {
                        $customer->save();
                    }
                }

                $totalCount = Customer::count();
                $this->command->info("Created " . min($i + $chunkSize, $count) . " customers");

                $changeRate = $this->calculateChangeRate($totalCount, $previousTotalCount);

                // イベントは発火されませんが、コードは維持
                event(new CustomerCountUpdated($totalCount, $previousTotalCount, $changeRate));
            }

            // 最終的な総数と変化率をキャッシュに保存
            Cache::put('previous_total_count', $totalCount, now()->addDay());
            Cache::put('change_rate', 25, now()->addDay());

            $this->command->info("Total customers after seeding: " . $totalCount);
            $this->command->info("Initial change rate set to 25%");
        } catch (\Exception $e) {
            $this->command->error("Seeding error: " . $e->getMessage());
            throw $e;
        }
    }

    private function calculateChangeRate($currentCount, $previousCount)
    {
        if ($previousCount == 0) {
            return 100;
        }
        $changeRate = (($currentCount - $previousCount) / $previousCount) * 100;
        return round($changeRate, 2);
    }
}
