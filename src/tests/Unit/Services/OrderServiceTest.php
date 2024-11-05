<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;
    private User $user;
    private Customer $customer;
    private Product $product1;
    private Product $product2;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーを作成
        $this->user = User::factory()->staff()->create();
        Auth::login($this->user);

        // テスト用の顧客を作成
        $this->customer = Customer::factory()->create();

        // テスト用の商品を作成
        $this->product1 = Product::factory()->create([
            'price' => 1000,
            'stockQuantity' => 100,
            'is_active' => true
        ]);

        $this->product2 = Product::factory()->create([
            'price' => 2000,
            'stockQuantity' => 50,
            'is_active' => true
        ]);

        // テスト用のキャンペーンを作成
        $this->campaign = Campaign::factory()->create([
            'discountRate' => 10,
            'is_active' => true,
        ]);

        $this->orderService = new OrderService();
    }

    /**
     * @test
     */
    public function 基本的な注文を作成できること()
    {
        $orderData = [
            'customerId' => $this->customer->id,
            'orderItems' => [
                [
                    'productId' => $this->product1->id,
                    'quantity' => 2
                ]
            ]
        ];

        $order = $this->orderService->createOrder($orderData);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->customer->id, $order->customerId);
        $this->assertEquals($this->user->id, $order->userId);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(2000, $order->totalAmount); // 1000円 × 2個
        $this->assertEquals(0, $order->discountApplied);
        $this->assertCount(1, $order->orderItems);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{4}$/', $order->orderNumber);
    }

    /**
     * @test
     */
    public function キャンペーン適用時に割引が正しく計算されること()
    {
        $orderData = [
            'customerId' => $this->customer->id,
            'campaignId' => $this->campaign->id,
            'orderItems' => [
                [
                    'productId' => $this->product1->id,
                    'quantity' => 2
                ]
            ]
        ];

        $order = $this->orderService->createOrder($orderData);

        $expectedTotal = 2000; // 1000円 × 2個
        $expectedDiscount = floor($expectedTotal * 0.1); // 10%割引
        $this->assertEquals($expectedTotal - $expectedDiscount, $order->totalAmount);
        $this->assertEquals($expectedDiscount, $order->discountApplied);
    }

    /**
     * @test
     */
    public function 複数商品の注文を作成できること()
    {
        $orderData = [
            'customerId' => $this->customer->id,
            'orderItems' => [
                [
                    'productId' => $this->product1->id,
                    'quantity' => 2
                ],
                [
                    'productId' => $this->product2->id,
                    'quantity' => 1
                ]
            ]
        ];

        $order = $this->orderService->createOrder($orderData);

        $this->assertEquals(4000, $order->totalAmount); // (1000円 × 2) + (2000円 × 1)
        $this->assertCount(2, $order->orderItems);
    }

    /**
     * @test
     */
    public function 注文明細を更新できること()
    {
        // 初期注文を作成
        $initialOrderData = [
            'customerId' => $this->customer->id,
            'orderItems' => [
                [
                    'productId' => $this->product1->id,
                    'quantity' => 1
                ]
            ]
        ];
        $order = $this->orderService->createOrder($initialOrderData);

        // 注文明細を更新
        $updateData = [
            [
                'productId' => $this->product2->id,
                'quantity' => 2
            ]
        ];

        $updatedOrder = $this->orderService->updateOrderItems($order, $updateData);

        $this->assertEquals(4000, $updatedOrder->totalAmount); // 2000円 × 2個
        $this->assertCount(1, $updatedOrder->orderItems);
        $this->assertEquals($this->product2->id, $updatedOrder->orderItems[0]->productId);
        $this->assertEquals(2, $updatedOrder->orderItems[0]->quantity);
    }

    /**
     * @test
     */
    public function 存在しない商品での注文作成時にエラーとなること()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $orderData = [
            'customerId' => $this->customer->id,
            'orderItems' => [
                [
                    'productId' => 'non-existent-id',
                    'quantity' => 1
                ]
            ]
        ];

        $this->orderService->createOrder($orderData);
    }

    /**
     * @test
     */
    public function 注文番号が一意に生成されること()
    {
        $orderNumbers = [];
        for ($i = 0; $i < 10; $i++) {
            $orderData = [
                'customerId' => $this->customer->id,
                'orderItems' => [
                    [
                        'productId' => $this->product1->id,
                        'quantity' => 1
                    ]
                ]
            ];

            $order = $this->orderService->createOrder($orderData);
            $this->assertNotContains($order->orderNumber, $orderNumbers);
            $orderNumbers[] = $order->orderNumber;
        }
    }

    /**
     * @test
     */
    public function キャンペーンがない場合は割引が適用されないこと()
    {
        $orderData = [
            'customerId' => $this->customer->id,
            'orderItems' => [
                [
                    'productId' => $this->product1->id,
                    'quantity' => 2
                ]
            ]
        ];

        $order = $this->orderService->createOrder($orderData);

        $this->assertEquals(2000, $order->totalAmount);
        $this->assertEquals(0, $order->discountApplied);
    }

    /**
     * @test
     */
    public function 注文更新時に古い注文明細が正しく削除されること()
    {
        // 初期注文を作成
        $initialOrderData = [
            'customerId' => $this->customer->id,
            'orderItems' => [
                [
                    'productId' => $this->product1->id,
                    'quantity' => 2
                ],
                [
                    'productId' => $this->product2->id,
                    'quantity' => 1
                ]
            ]
        ];
        $originalOrder = $this->orderService->createOrder($initialOrderData);
        $originalItemIds = $originalOrder->orderItems->pluck('id')->toArray();

        // 注文明細を更新
        $updateData = [
            [
                'productId' => $this->product1->id,
                'quantity' => 1
            ]
        ];

        $updatedOrder = $this->orderService->updateOrderItems($originalOrder, $updateData);

        // 古い明細が削除され、新しい明細のみ存在することを確認
        foreach ($originalItemIds as $oldItemId) {
            $this->assertDatabaseMissing('order_items', ['id' => $oldItemId]);
        }
        $this->assertCount(1, $updatedOrder->orderItems);
    }

    /**
     * @test
     */
    public function 更新時にキャンペーン割引が正しく再計算されること()
    {
        // キャンペーン付きの初期注文を作成
        $initialOrderData = [
            'customerId' => $this->customer->id,
            'campaignId' => $this->campaign->id,
            'orderItems' => [
                [
                    'productId' => $this->product1->id,
                    'quantity' => 1
                ]
            ]
        ];
        $order = $this->orderService->createOrder($initialOrderData);

        // 注文明細を更新（金額を増やす）
        $updateData = [
            [
                'productId' => $this->product2->id,
                'quantity' => 2
            ]
        ];

        $updatedOrder = $this->orderService->updateOrderItems($order, $updateData);

        $expectedTotal = 4000; // 2000円 × 2個
        $expectedDiscount = floor($expectedTotal * 0.1); // 10%割引
        $this->assertEquals($expectedTotal - $expectedDiscount, $updatedOrder->totalAmount);
        $this->assertEquals($expectedDiscount, $updatedOrder->discountApplied);
    }
}
