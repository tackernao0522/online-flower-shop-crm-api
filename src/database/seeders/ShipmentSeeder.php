<?php

namespace Database\Seeders;

use App\Models\Shipment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 準備中の配送を作成
            Shipment::factory()
                ->count(3)
                ->preparing()
                ->create();

            // 発送済みの配送を作成
            Shipment::factory()
                ->count(5)
                ->shipped()
                ->create();

            // 配達完了の配送を作成
            Shipment::factory()
                ->count(7)
                ->delivered()
                ->create();

            $this->command->info('Created ' . Shipment::count() . ' shipments');
        });
    }
}
