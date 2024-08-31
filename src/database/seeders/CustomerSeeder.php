<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $count = 1000;
        $chunkSize = 100;

        for ($i = 0; $i < $count; $i += $chunkSize) {
            Customer::factory()->count(min($chunkSize, $count - $i))->create();
            $this->command->info("Created " . min($i + $chunkSize, $count) . " customers");
        }
    }
}
