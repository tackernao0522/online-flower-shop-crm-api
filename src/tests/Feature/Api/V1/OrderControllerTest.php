<?php

namespace Tests\Feature\Api\V1;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Customer;
use App\Models\Campaign;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private $orderService;
    private $admin;
    private $manager;
    private $staff;
    private $customer;
    private $product;
    private $campaign;
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーの作成
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->staff = User::factory()->staff()->create();

        // テスト用顧客の作成
        $this->customer = Customer::factory()->create();

        // テスト用商品の作成
        $this->product = Product::factory()->create([
            'price' => 1000,
            'stockQuantity' => 100,
            'is_active' => true
        ]);

        // テスト用キャンペーンの作成
        $this->campaign = Campaign::factory()->create([
            'discountRate' => 10,
            'is_active' => true
        ]);

        // テスト用注文の作成
        $this->order = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->staff->id,
            'status' => Order::STATUS_PENDING,
            'totalAmount' => $this->product->price * 2
        ]);

        // 注文明細の作成
        OrderItem::factory()->create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 2,
            'unitPrice' => $this->product->price
        ]);

        $this->orderService = $this->mock(OrderService::class);
    }

    /**
     * @test
     */
    public function 認証されていないユーザーが注文一覧にアクセスできないこと()
    {
        $response = $this->getJson('/api/v1/orders');
        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function 注文一覧を正常に取得できること()
    {
        $orders = [];
        for ($i = 0; $i < 4; $i++) {
            $order = Order::factory()->create([
                'customerId' => $this->customer->id,
                'userId' => $this->staff->id,
                'totalAmount' => $this->product->price * 2
            ]);

            OrderItem::factory()->create([
                'orderId' => $order->id,
                'productId' => $this->product->id,
                'quantity' => 2,
                'unitPrice' => $this->product->price
            ]);

            $orders[] = $order;
        }

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson('/api/v1/orders');

        $responseData = $response->json();
        $this->assertIsArray($responseData['data']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
                'stats'
            ]);
    }

    /**
     * @test
     */
    // public function 日付範囲で注文を検索できること()
    // {
    //     $startDate = now()->subDays(7);
    //     $endDate = now();

    //     // 検索範囲内の注文を作成
    //     for ($i = 0; $i < 3; $i++) {
    //         $order = Order::factory()->create([
    //             'customerId' => $this->customer->id,
    //             'userId' => $this->staff->id,
    //             'orderDate' => $startDate->copy()->addDays($i),
    //             'totalAmount' => $this->product->price * 2
    //         ]);

    //         OrderItem::factory()->create([
    //             'orderId' => $order->id,
    //             'productId' => $this->product->id,
    //             'quantity' => 2,
    //             'unitPrice' => $this->product->price
    //         ]);
    //     }

    //     $response = $this->withHeaders($this->actingAsUser($this->admin))
    //         ->getJson("/api/v1/orders?start_date={$startDate->format('Y-m-d')}&end_date={$endDate->format('Y-m-d')}");

    //     $response->assertStatus(200)
    //         ->assertJsonStructure([
    //             'data',
    //             'meta',
    //             'stats'
    //         ]);

    //     $responseJson = $response->json();
    //     $this->assertArrayHasKey('data', $responseJson);

    //     // データが配列であることを確認
    //     $this->assertIsArray($responseJson['data']);
    //     $orders = $responseJson['data'];

    //     foreach ($orders as $order) {
    //         $this->assertIsArray($order, 'Order data should be an array');
    //         $this->assertArrayHasKey('orderDate', $order, 'Order should have orderDate key');
    //         $orderDate = Carbon::parse($order['orderDate'])->startOfDay();
    //         $this->assertTrue(
    //             $orderDate->between($startDate->startOfDay(), $endDate->endOfDay()),
    //             "注文日 {$orderDate} が検索範囲外です"
    //         );
    //     }
    // }

    /**
     * @test
     */
    public function 新規注文を正常に作成できること()
    {
        $orderData = [
            'customerId' => $this->customer->id,
            'orderItems' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 2
                ]
            ],
            'campaignId' => $this->campaign->id
        ];

        $orderItem = new OrderItem([
            'quantity' => 2,
            'unitPrice' => $this->product->price,
            'productId' => $this->product->id
        ]);
        $orderItem->setRelation('product', $this->product);

        $expectedOrder = Order::factory()->make([
            'id' => 'test-id',
            'orderNumber' => 'ORD-20240101-TEST',
            'customerId' => $this->customer->id,
            'userId' => $this->staff->id,
            'totalAmount' => $this->product->price * 2,
            'status' => Order::STATUS_PENDING
        ]);
        $expectedOrder->setRelation('orderItems', collect([$orderItem]));
        $expectedOrder->setRelation('customer', $this->customer);

        $this->orderService
            ->shouldReceive('createOrder')
            ->once()
            ->andReturn($expectedOrder);

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->postJson('/api/v1/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'orderNumber',
                'totalAmount',
                'status',
                'orderItems' => [
                    '*' => [
                        'quantity',
                        'unitPrice',
                        'product'
                    ]
                ],
                'customer'
            ]);
    }

    /**
     * @test
     */
    public function 注文明細を正常に更新できること()
    {
        // テスト用の StatsLog レコードを作成
        \App\Models\StatsLog::create([
            'metric_type' => 'order_count',
            'current_value' => 100,
            'previous_value' => 90,
            'change_rate' => 11.11,
            'recorded_at' => now()
        ]);

        \App\Models\StatsLog::create([
            'metric_type' => 'sales',
            'current_value' => 10000,
            'previous_value' => 9000,
            'change_rate' => 11.11,
            'recorded_at' => now()
        ]);

        $updateData = [
            'orderItems' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 3
                ]
            ]
        ];

        // モックを作成
        $updatedOrder = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->staff->id,
            'status' => Order::STATUS_PENDING,
            'totalAmount' => $this->product->price * 3
        ]);

        $updatedOrderItem = OrderItem::factory()->create([
            'orderId' => $updatedOrder->id,
            'productId' => $this->product->id,
            'quantity' => 3,
            'unitPrice' => $this->product->price
        ]);

        $updatedOrder->setRelation('orderItems', collect([$updatedOrderItem]));

        // OrderServiceのモックを設定
        $this->orderService
            ->shouldReceive('updateOrderItems')
            ->once()
            ->with(
                Mockery::on(function ($order) {
                    return $order->id === $this->order->id;
                }),
                Mockery::on(function ($items) use ($updateData) {
                    return $items === $updateData['orderItems'];
                })
            )
            ->andReturn($updatedOrder);

        // StatsServiceのモックを設定
        $this->mock(\App\Services\StatsService::class, function ($mock) {
            $mock->shouldReceive('updateStats')
                ->andReturn([
                    'currentValue' => 100,
                    'previousValue' => 90,
                    'changeRate' => 11.11
                ]);
        });

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->putJson("/api/v1/orders/{$this->order->id}/items", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $updatedOrder->id,
                'totalAmount' => $updatedOrder->totalAmount,
                'orderItems' => [
                    [
                        'quantity' => 3,
                        'unitPrice' => $this->product->price,
                    ]
                ]
            ]);
    }

    /**
     * @test
     */
    public function 配達済みの注文は明細を更新できないこと()
    {
        $deliveredOrder = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->staff->id,
            'status' => Order::STATUS_DELIVERED
        ]);

        $updateData = [
            'orderItems' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 3
                ]
            ]
        ];

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->putJson("/api/v1/orders/{$deliveredOrder->id}/items", $updateData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'この注文は編集できません']);
    }

    /**
     * @test
     */
    public function 注文ステータスを正常に更新できること()
    {
        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->putJson("/api/v1/orders/{$this->order->id}/status", [
                'status' => Order::STATUS_PROCESSING
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', Order::STATUS_PROCESSING);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => Order::STATUS_PROCESSING
        ]);
    }

    /**
     * @test
     */
    public function 配達済みの注文はキャンセルできないこと()
    {
        $deliveredOrder = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->staff->id,
            'status' => Order::STATUS_DELIVERED
        ]);

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->putJson("/api/v1/orders/{$deliveredOrder->id}/status", [
                'status' => Order::STATUS_CANCELLED
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * @test
     */
    public function 無効な注文ステータスは設定できないこと()
    {
        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->putJson("/api/v1/orders/{$this->order->id}/status", [
                'status' => 'INVALID_STATUS'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * @test
     */
    public function 配達済みの注文は削除できないこと()
    {
        $deliveredOrder = Order::factory()->create([
            'status' => Order::STATUS_DELIVERED,
            'customerId' => $this->customer->id,
            'userId' => $this->staff->id
        ]);

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->deleteJson("/api/v1/orders/{$deliveredOrder->id}");

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'ORDER_CANNOT_BE_DELETED',
                    'message' => 'この注文は削除できません'
                ]
            ]);

        $this->assertDatabaseHas('orders', ['id' => $deliveredOrder->id]);
    }

    /**
     * @test
     */
    public function 処理中の注文を削除できること()
    {
        $processingOrder = Order::factory()->create([
            'status' => Order::STATUS_PROCESSING,
            'customerId' => $this->customer->id,
            'userId' => $this->staff->id
        ]);

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->deleteJson("/api/v1/orders/{$processingOrder->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('orders', ['id' => $processingOrder->id]);
    }

    /**
     * @test
     */
    // public function 金額範囲で注文を検索できること()
    // {
    //     // 異なる金額の注文を作成
    //     $amounts = [5000, 10000, 15000, 20000];
    //     foreach ($amounts as $amount) {
    //         Order::factory()->create([
    //             'customerId' => $this->customer->id,
    //             'userId' => $this->staff->id,
    //             'totalAmount' => $amount
    //         ]);
    //     }

    //     $minAmount = 10000;
    //     $maxAmount = 20000;

    //     $response = $this->withHeaders($this->actingAsUser($this->admin))
    //         ->getJson("/api/v1/orders?min_amount={$minAmount}&max_amount={$maxAmount}");

    //     $response->assertStatus(200)
    //         ->assertJsonStructure([
    //             'data',
    //             'meta',
    //             'stats'
    //         ]);

    //     $responseJson = $response->json();
    //     $this->assertArrayHasKey('data', $responseJson);
    //     $orders = $responseJson['data'];

    //     $this->assertIsArray($orders);
    //     $this->assertNotEmpty($orders);

    //     foreach ($orders as $order) {
    //         $this->assertIsArray($order);
    //         $this->assertArrayHasKey('totalAmount', $order);
    //         $amount = (int)$order['totalAmount'];
    //         $this->assertGreaterThanOrEqual($minAmount, $amount);
    //         $this->assertLessThanOrEqual($maxAmount, $amount);
    //     }
    // }

    protected function actingAsUser($user)
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => 'Bearer ' . $token];
    }
}
