<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;
    private Product $activeProduct;
    private Product $inactiveProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = new ProductService();

        // アクティブな商品を作成
        $this->activeProduct = Product::factory()->create([
            'name' => 'アクティブ商品',
            'description' => '商品の説明文',
            'price' => 1000,
            'stockQuantity' => 10,
            'category' => '花束',
            'is_active' => true,
        ]);

        // 非アクティブな商品を作成
        $this->inactiveProduct = Product::factory()->inactive()->create([
            'name' => '非アクティブ商品',
            'category' => '花束',
        ]);
    }

    /**
     * @test
     */
    public function 商品一覧を取得できること()
    {
        // 追加のテストデータを作成
        Product::factory()->count(5)->create(['is_active' => true]);
        Product::factory()->count(3)->inactive()->create();

        $result = $this->productService->getProducts([]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(6, $result->total()); // アクティブな商品のみ（5 + 1）
        $this->assertTrue($result->items()[0]->is_active);
    }

    /**
     * @test
     */
    public function カテゴリで商品を絞り込めること()
    {
        // 異なるカテゴリの商品を作成
        Product::factory()->create([
            'category' => '観葉植物',
            'is_active' => true,
        ]);

        $result = $this->productService->getProducts(['category' => '花束']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('花束', $result->items()[0]->category);
    }

    /**
     * @test
     */
    public function ページネーションが正しく機能すること()
    {
        // 20件の商品を作成
        Product::factory()->count(20)->create(['is_active' => true]);

        // 1ページあたり10件で取得
        $result = $this->productService->getProducts([], 10);

        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(21, $result->total()); // 既存の1件 + 新規20件
        $this->assertEquals(3, $result->lastPage());
    }

    /**
     * @test
     */
    public function アクティブな商品の詳細を取得できること()
    {
        $result = $this->productService->getProductDetails($this->activeProduct);

        $this->assertEquals($this->activeProduct->id, $result->id);
        $this->assertEquals('アクティブ商品', $result->name);
        $this->assertTrue($result->is_active);
    }

    /**
     * @test
     */
    public function 非アクティブな商品の詳細を取得しようとすると例外が発生すること()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('指定された商品は現在利用できません');

        $this->productService->getProductDetails($this->inactiveProduct);
    }

    /**
     * @test
     */
    public function アクティブな商品の在庫状況を取得できること()
    {
        $result = $this->productService->getStockStatus($this->activeProduct);

        $this->assertEquals([
            'id' => $this->activeProduct->id,
            'name' => 'アクティブ商品',
            'stockQuantity' => 10,
            'is_in_stock' => true
        ], $result);
    }

    /**
     * @test
     */
    public function 非アクティブな商品の在庫状況を取得しようとすると例外が発生すること()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('指定された商品は現在利用できません');

        $this->productService->getStockStatus($this->inactiveProduct);
    }

    /**
     * @test
     */
    public function 在庫切れ商品の在庫状況を正しく取得できること()
    {
        $outOfStockProduct = Product::factory()->create([
            'name' => '在庫切れ商品',
            'stockQuantity' => 0,
            'is_active' => true,
        ]);

        $result = $this->productService->getStockStatus($outOfStockProduct);

        $this->assertEquals([
            'id' => $outOfStockProduct->id,
            'name' => '在庫切れ商品',
            'stockQuantity' => 0,
            'is_in_stock' => false
        ], $result);
    }

    /**
     * @test
     */
    public function 不正なカテゴリで検索すると空の結果が返されること()
    {
        $result = $this->productService->getProducts(['category' => '存在しないカテゴリ']);

        $this->assertEquals(0, $result->total());
        $this->assertEmpty($result->items());
    }
}
