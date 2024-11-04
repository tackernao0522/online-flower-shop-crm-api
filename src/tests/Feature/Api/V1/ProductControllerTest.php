<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private $productService;
    private $admin;
    private $manager;
    private $staff;
    private $activeProduct;
    private $inactiveProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーの作成
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->staff = User::factory()->staff()->create();

        // テスト用商品の作成
        $this->activeProduct = Product::factory()->create([
            'name' => 'テスト商品',
            'description' => '商品説明',
            'price' => 1000,
            'stockQuantity' => 10,
            'category' => '花束',
            'is_active' => true,
        ]);

        $this->inactiveProduct = Product::factory()->inactive()->create();

        $this->productService = Mockery::mock(ProductService::class);
        $this->app->instance(ProductService::class, $this->productService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function actingAsUser($user)
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * @test
     * @group products
     */
    public function 認証されていないユーザーが商品一覧にアクセスできないこと()
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * @test
     * @group products
     */
    public function アクティブな商品一覧を正常に取得できること()
    {
        $products = collect([
            new Product([
                'name' => 'テスト商品1',
                'description' => '商品説明1',
                'price' => 1000,
                'stockQuantity' => 10,
                'category' => '花束',
                'is_active' => true,
            ]),
            new Product([
                'name' => 'テスト商品2',
                'description' => '商品説明2',
                'price' => 2000,
                'stockQuantity' => 20,
                'category' => '花束',
                'is_active' => true,
            ]),
        ]);

        $paginatedProducts = new LengthAwarePaginator(
            $products,
            2,
            15,
            1
        );

        $this->productService
            ->shouldReceive('getProducts')
            ->once()
            ->with(['category' => null], 15)
            ->andReturn($paginatedProducts);

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'name',
                        'description',
                        'price',
                        'stockQuantity',
                        'category',
                        'is_active',
                    ]
                ],
                'current_page',
                'total'
            ]);
    }

    /**
     * @test
     * @group products
     */
    public function カテゴリーを指定して商品一覧を取得できること()
    {
        $products = collect([
            new Product([
                'name' => 'テスト商品1',
                'description' => '商品説明1',
                'price' => 1000,
                'stockQuantity' => 10,
                'category' => '花束',
                'is_active' => true,
            ])
        ]);

        $paginatedProducts = new LengthAwarePaginator(
            $products,
            1,
            15,
            1
        );

        $this->productService
            ->shouldReceive('getProducts')
            ->once()
            ->with(['category' => '花束'], 15)
            ->andReturn($paginatedProducts);

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->getJson('/api/v1/products?category=花束');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', '花束');
    }

    /**
     * @test
     * @group products
     */
    public function アクティブな商品の詳細を正常に取得できること()
    {
        $this->productService
            ->shouldReceive('getProductDetails')
            ->once()
            ->with(Mockery::type(Product::class))
            ->andReturn($this->activeProduct);

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->getJson("/api/v1/products/{$this->activeProduct->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'description',
                'price',
                'stockQuantity',
                'category',
                'is_active',
            ]);
    }

    /**
     * @test
     * @group products
     */
    public function 非アクティブな商品の詳細を取得した場合は404エラーとなること()
    {
        $this->productService
            ->shouldReceive('getProductDetails')
            ->once()
            ->with(Mockery::type(Product::class))
            ->andThrow(new ModelNotFoundException('指定された商品は現在利用できません'));

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson("/api/v1/products/{$this->inactiveProduct->id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => '指定された商品は現在利用できません'
            ]);
    }

    /**
     * @test
     * @group products
     */
    public function アクティブな商品の在庫状況を正常に取得できること()
    {
        $stockStatus = [
            'id' => $this->activeProduct->id,
            'name' => $this->activeProduct->name,
            'stockQuantity' => $this->activeProduct->stockQuantity,
            'is_in_stock' => true
        ];

        $this->productService
            ->shouldReceive('getStockStatus')
            ->once()
            ->with(Mockery::type(Product::class))
            ->andReturn($stockStatus);

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->getJson("/api/v1/products/{$this->activeProduct->id}/stock");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'stockQuantity',
                'is_in_stock'
            ])
            ->assertJson($stockStatus);
    }

    /**
     * @test
     * @group products
     */
    public function 非アクティブな商品の在庫状況を取得した場合は404エラーとなること()
    {
        $this->productService
            ->shouldReceive('getStockStatus')
            ->once()
            ->with(Mockery::type(Product::class))
            ->andThrow(new ModelNotFoundException('指定された商品は現在利用できません'));

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson("/api/v1/products/{$this->inactiveProduct->id}/stock");

        $response->assertStatus(404)
            ->assertJson([
                'message' => '指定された商品は現在利用できません'
            ]);
    }
}
