<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use App\Models\Campaign;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private Customer $customer;
    private User $user;
    private Campaign $campaign;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーを作成
        $this->user = User::factory()->staff()->create();

        // テスト用の顧客を作成
        $this->customer = Customer::factory()->create();

        // テスト用のキャンペーンを作成
        $this->campaign = Campaign::factory()->create([
            'discountRate' => 10
        ]);

        // テスト用の商品を作成
        $this->product = Product::factory()->create([
            'price' => 1000
        ]);

        // テスト用の注文を作成
        $this->order = new Order([
            'orderNumber' => 'TEST-' . now()->format('Ymd') . '-0001',
            'orderDate' => now(),
            'totalAmount' => 2000,
            'status' => Order::STATUS_PENDING,
            'discountApplied' => 200,
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'campaignId' => $this->campaign->id
        ]);
        $this->order->save();
    }

    /**
     * @test
     */
    public function すべての注文ステータスが定義されていること()
    {
        $statuses = Order::getAvailableStatuses();

        $this->assertEquals([
            Order::STATUS_PENDING,
            Order::STATUS_PROCESSING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED
        ], $statuses);
    }

    /**
     * @test
     */
    public function 注文から関連する顧客を取得できること()
    {
        $customer = $this->order->customer;

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals($this->customer->id, $customer->id);
    }

    /**
     * @test
     */
    public function 注文から関連するユーザーを取得できること()
    {
        $user = $this->order->user;

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->user->id, $user->id);
    }

    /**
     * @test
     */
    public function 注文から関連するキャンペーンを取得できること()
    {
        $campaign = $this->order->campaign;

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals($this->campaign->id, $campaign->id);
    }

    /**
     * @test
     */
    public function 注文から関連する注文明細を取得できること()
    {
        // 注文明細を作成
        $orderItem1 = OrderItem::factory()->create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 2,
            'unitPrice' => 1000
        ]);

        $orderItem2 = OrderItem::factory()->create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 1,
            'unitPrice' => 1000
        ]);

        $orderItems = $this->order->orderItems;

        $this->assertCount(2, $orderItems);
        $this->assertTrue($orderItems->contains($orderItem1));
        $this->assertTrue($orderItems->contains($orderItem2));
    }

    /**
     * @test
     */
    public function 注文合計が正しく計算されること()
    {
        // 注文明細を作成
        OrderItem::factory()->create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 2,
            'unitPrice' => 1000
        ]);

        OrderItem::factory()->create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 1,
            'unitPrice' => 1000
        ]);

        // リレーションをリフレッシュ
        $this->order->refresh();

        $total = $this->order->calculateTotal();

        $this->assertEquals(3000, $total); // (1000円 × 2) + (1000円 × 1)
    }

    /**
     * @test
     */
    public function 保留中の注文はキャンセル可能であること()
    {
        $this->order->status = Order::STATUS_PENDING;
        $this->assertTrue($this->order->canBeCancelled());
    }

    /**
     * @test
     */
    public function 処理中の注文はキャンセル可能であること()
    {
        $this->order->status = Order::STATUS_PROCESSING;
        $this->assertTrue($this->order->canBeCancelled());
    }

    /**
     * @test
     */
    public function 確定済みの注文はキャンセル不可であること()
    {
        $this->order->status = Order::STATUS_CONFIRMED;
        $this->assertFalse($this->order->canBeCancelled());
    }

    /**
     * @test
     */
    public function 日付範囲で注文を絞り込めること()
    {
        // 過去の注文を作成
        Order::factory()->create([
            'orderDate' => now()->subDays(5),
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        // 未来の注文を作成
        Order::factory()->create([
            'orderDate' => now()->addDays(5),
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        $results = Order::dateRange(
            now()->subDay(),
            now()->addDay()
        )->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->order));
    }

    /**
     * @test
     */
    public function 金額範囲で注文を絞り込めること()
    {
        // 少額の注文を作成
        Order::factory()->create([
            'totalAmount' => 1000,
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        // 高額の注文を作成
        Order::factory()->create([
            'totalAmount' => 5000,
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        $results = Order::amountRange(1500, 2500)->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->order));
    }

    /**
     * @test
     */
    public function ステータスで注文を絞り込めること()
    {
        // 異なるステータスの注文を作成
        Order::factory()->create([
            'status' => Order::STATUS_DELIVERED,
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        $results = Order::withStatus(Order::STATUS_PENDING)->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->order));
    }

    /**
     * @test
     */
    public function 注文番号で注文を検索できること()
    {
        // 異なる注文番号の注文を作成
        Order::factory()->create([
            'orderNumber' => 'OTHER-' . now()->format('Ymd') . '-0001',
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        $results = Order::orderNumberLike('TEST-')->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->order));
    }

    /**
     * @test
     */
    public function 日時型が正しくキャストされること()
    {
        $order = new Order([
            'orderDate' => '2024-01-01 10:00:00',
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        $this->assertInstanceOf(Carbon::class, $order->orderDate);
        $this->assertEquals('2024-01-01 10:00:00', $order->orderDate->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function 金額が整数型にキャストされること()
    {
        $order = new Order([
            'totalAmount' => '2000',
            'discountApplied' => '200',
            'customerId' => $this->customer->id,
            'userId' => $this->user->id
        ]);

        $this->assertIsInt($order->totalAmount);
        $this->assertIsInt($order->discountApplied);
        $this->assertEquals(2000, $order->totalAmount);
        $this->assertEquals(200, $order->discountApplied);
    }

    /**
     * @test
     */
    public function 注文を削除するとソフトデリートされること()
    {
        $this->order->delete();

        $this->assertSoftDeleted($this->order);
        $this->assertDatabaseHas('orders', ['id' => $this->order->id]);
    }

    /**
     * @test
     */
    public function 削除された注文を取得できないこと()
    {
        $this->order->delete();

        $this->assertNull(Order::find($this->order->id));
    }

    /**
     * @test
     */
    public function 削除された注文をwithTrashedで取得できること()
    {
        $this->order->delete();

        $this->assertNotNull(Order::withTrashed()->find($this->order->id));
    }
}
