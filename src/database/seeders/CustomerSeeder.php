<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use App\Events\CustomerCountUpdated;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $count = 1000;
        $chunkSize = 100;

        $totalCount = 0;
        $previousTotalCount = 0;

        for ($i = 0; $i < $count; $i += $chunkSize) {
            $previousTotalCount = $totalCount;
            Customer::factory()->count(min($chunkSize, $count - $i))->create();
            $totalCount = Customer::count();
            $this->command->info("Created " . min($i + $chunkSize, $count) . " customers");

            $changeRate = $this->calculateChangeRate($totalCount, $previousTotalCount);

            event(new CustomerCountUpdated($totalCount, $previousTotalCount, $changeRate));
        }

        // 最終的な総数と変化率をキャッシュに保存
        Cache::put('previous_total_count', $totalCount, now()->addDay());
        Cache::put('change_rate', 25, now()->addDay());  // 初期値を25%に設定

        $this->command->info("Total customers after seeding: " . $totalCount);
        $this->command->info("Initial change rate set to 25%");
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
