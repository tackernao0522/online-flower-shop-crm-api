<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // テストデータをクリーンアップ
        Product::query()->delete();

        $this->product = Product::factory()->create([
            'name' => 'テスト商品',
            'description' => '商品の説明文',
            'price' => 1000,
            'stockQuantity' => 10,
            'category' => '花束',
            'is_active' => true,
        ]);
    }

    /**
     * @test
     */
    public function 商品名は100文字以内であること()
    {
        $longName = str_repeat('あ', 100);
        $product = Product::factory()->create(['name' => $longName]);

        $this->assertEquals(100, mb_strlen($product->name));
    }

    /**
     * @test
     */
    public function カテゴリは50文字以内であること()
    {
        $longCategory = str_repeat('あ', 50);
        $product = Product::factory()->create(['category' => $longCategory]);

        $this->assertEquals(50, mb_strlen($product->category));
    }

    /**
     * @test
     */
    public function 価格は100円単位で設定されること()
    {
        $products = Product::factory()->count(10)->create();

        foreach ($products as $product) {
            $this->assertEquals(0, $product->price % 100);
        }
    }

    /**
     * @test
     */
    public function 在庫数のデフォルト値は0であること()
    {
        // factoryのデフォルト動作をスキップし、必要最小限のデータで作成
        $product = Product::create([
            'name' => 'テスト商品',
            'description' => '説明',
            'price' => 1000,
            'category' => '花束',
            'is_active' => true,
        ]);

        $product->refresh(); // データベースから再読み込み
        $this->assertEquals(0, $product->stockQuantity);
    }

    /**
     * @test
     */
    public function is_activeのデフォルト値はtrueであること()
    {
        // factoryのデフォルト動作をスキップし、必要最小限のデータで作成
        $product = Product::create([
            'name' => 'テスト商品',
            'description' => '説明',
            'price' => 1000,
            'category' => '花束',
            'stockQuantity' => 10,
        ]);

        $product->refresh(); // データベースから再読み込み
        $this->assertTrue($product->is_active);
    }

    /**
     * @test
     */
    public function Factory定義の在庫切れ状態が正しく機能すること()
    {
        $product = Product::factory()->outOfStock()->create();

        $this->assertEquals(0, $product->stockQuantity);
        $this->assertFalse(Product::inStock()->where('id', $product->id)->exists());
    }

    /**
     * @test
     */
    public function Factory定義の非アクティブ状態が正しく機能すること()
    {
        $product = Product::factory()->inactive()->create();

        $this->assertFalse($product->is_active);
        $this->assertFalse(Product::active()->where('id', $product->id)->exists());
    }

    /**
     * @test
     */
    public function Factory定義のプレミアム商品が正しく機能すること()
    {
        $product = Product::factory()->premium()->create();

        $this->assertGreaterThanOrEqual(50000, $product->price);
        $this->assertLessThanOrEqual(100000, $product->price);
        $this->assertEquals(0, $product->price % 100);
    }

    /**
     * @test
     */
    public function 全ての有効なカテゴリで商品が作成できること()
    {
        // テスト前にデータをクリア
        Product::query()->delete();

        $categories = [
            '花束',
            'アレンジメント',
            '鉢植え',
            'ブライダルブーケ',
            'リース',
            '観葉植物',
        ];

        foreach ($categories as $category) {
            $product = Product::factory()->create(['category' => $category]);
            $this->assertEquals($category, $product->category);
        }

        $products = Product::all();
        $this->assertEquals(count($categories), $products->count());
    }


    /**
     * @test
     */
    public function Factory生成の商品は価格が1000円から50000円の範囲内であること()
    {
        $products = Product::factory()->count(50)->create();

        foreach ($products as $product) {
            $this->assertGreaterThanOrEqual(1000, $product->price);
            $this->assertLessThanOrEqual(50000, $product->price);
        }
    }

    /**
     * @test
     */
    public function 価格がマイナスの場合は作成できないこと()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('価格は0以上である必要があります。');

        Product::create([
            'name' => 'テスト商品',
            'description' => '説明',
            'price' => -1000,
            'stockQuantity' => 10,
            'category' => '花束',
            'is_active' => true,
        ]);
    }

    /**
     * @test
     */
    public function 在庫数がマイナスの場合は作成できないこと()
    {
        $this->expectException(\InvalidArgumentException::class);
        // または、カスタム例外を作成して使用することをお勧めします

        Product::create([
            'name' => 'テスト商品',
            'description' => '説明',
            'price' => 1000,
            'stockQuantity' => -10,
            'category' => '花束',
            'is_active' => true,
        ]);
    }
}
