<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 基本的な商品データ
            $this->createBasicProducts();

            // ランダムな商品データ
            $this->createRandomProducts();

            $totalCount = Product::count();
            $this->command->info("Total products after seeding: " . $totalCount);
        });
    }

    /**
     * 基本的な商品データを作成
     */
    private function createBasicProducts(): void
    {
        // 基本的な商品リスト
        $basicProducts = [
            [
                'name' => 'スタンダードブーケ',
                'description' => '季節の花々を使用した定番のブーケです。様々なシーンでご利用いただけます。',
                'price' => 5000,
                'stockQuantity' => 10,
                'category' => '花束',
                'is_active' => true,
            ],
            [
                'name' => 'バラのアレンジメント',
                'description' => '厳選されたバラを使用した華やかなアレンジメント。記念日やお祝いにぴったりです。',
                'price' => 8000,
                'stockQuantity' => 5,
                'category' => 'アレンジメント',
                'is_active' => true,
            ],
            [
                'name' => '胡蝶蘭（白）',
                'description' => '開店祝いや開業祝いに最適な白い胡蝶蘭です。高級感があり、長く楽しめます。',
                'price' => 30000,
                'stockQuantity' => 3,
                'category' => '鉢植え',
                'is_active' => true,
            ],
        ];

        foreach ($basicProducts as $product) {
            Product::firstOrCreate(
                ['name' => $product['name']],
                $product
            );
        }

        $this->command->info("Basic products created successfully");
    }

    /**
     * ランダムな商品データを作成
     */
    private function createRandomProducts(): void
    {
        $count = 20; // 生成する商品数
        $chunkSize = 5;

        try {
            for ($i = 0; $i < $count; $i += $chunkSize) {
                Product::factory()
                    ->count(min($chunkSize, $count - $i))
                    ->create();

                $this->command->info("Created " . min($i + $chunkSize, $count) . " random products");
            }

            // 在庫切れ商品を数点作成
            Product::factory()
                ->count(2)
                ->outOfStock()
                ->create();

            // プレミアム商品を数点作成
            Product::factory()
                ->count(2)
                ->premium()
                ->create();

            // 非アクティブ商品を数点作成
            Product::factory()
                ->count(2)
                ->inactive()
                ->create();
        } catch (\Exception $e) {
            $this->command->error("Seeding error: " . $e->getMessage());
            throw $e;
        }
    }
}
