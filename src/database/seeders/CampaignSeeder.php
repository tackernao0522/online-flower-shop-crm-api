<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 進行中のキャンペーン
            Campaign::factory()
                ->count(3)
                ->active()
                ->create();

            // 終了したキャンペーン
            Campaign::factory()
                ->count(2)
                ->past()
                ->create();

            // 将来のキャンペーン
            Campaign::factory()
                ->count(3)
                ->create();

            $this->command->info("Total campaigns created: " . Campaign::count());
        });
    }
}
