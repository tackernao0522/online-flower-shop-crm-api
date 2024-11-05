<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private Product $product;
    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーを作成
        $user = User::factory()->staff()->create();

        // テスト用の顧客を作成
        $customer = Customer::factory()->create();

        // テスト用の注文を作成
        $this->order = new Order([
            'customerId' => $customer->id,
            'userId' => $user->id,
            'status' => Order::STATUS_CONFIRMED,
            'orderNumber' => 'TEST-' . now()->format('Ymd') . '-0001',
            'orderDate' => now(),
            'totalAmount' => 1000,
            'discountApplied' => 0
        ]);
        $this->order->save();

        // テスト用の商品を作成
        $this->product = Product::factory()->create([
            'price' => 1000,
            'stockQuantity' => 100,
        ]);

        // テスト用の注文明細を作成
        $this->orderItem = new OrderItem([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 2,
            'unitPrice' => 1000,
        ]);
        $this->orderItem->save();
    }

    /**
     * @test
     */
    public function 注文明細から関連する注文を取得できること()
    {
        $relatedOrder = $this->orderItem->order;

        $this->assertInstanceOf(Order::class, $relatedOrder);
        $this->assertEquals($this->order->id, $relatedOrder->id);
        $this->assertEquals($this->order->orderNumber, $relatedOrder->orderNumber);
    }

    /**
     * @test
     */
    public function 注文明細から関連する商品を取得できること()
    {
        $relatedProduct = $this->orderItem->product;

        $this->assertInstanceOf(Product::class, $relatedProduct);
        $this->assertEquals($this->product->id, $relatedProduct->id);
        $this->assertEquals($this->product->price, $relatedProduct->price);
    }

    /**
     * @test
     */
    public function 小計が正しく計算されること()
    {
        $subtotal = $this->orderItem->calculateSubtotal();

        $this->assertEquals(2000, $subtotal); // 1000円 × 2個
    }

    /**
     * @test
     */
    public function 数量が整数型にキャストされること()
    {
        $orderItem = new OrderItem([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => '5', // 文字列として設定
            'unitPrice' => 1000,
        ]);

        $this->assertIsInt($orderItem->quantity);
        $this->assertEquals(5, $orderItem->quantity);
    }

    /**
     * @test
     */
    public function 単価が整数型にキャストされること()
    {
        $orderItem = new OrderItem([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 1,
            'unitPrice' => '1500', // 文字列として設定
        ]);

        $this->assertIsInt($orderItem->unitPrice);
        $this->assertEquals(1500, $orderItem->unitPrice);
    }

    /**
     * @test
     */
    public function 必要なフィールドがfillableであること()
    {
        $data = [
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 3,
            'unitPrice' => 2000,
        ];

        $orderItem = new OrderItem($data);
        $orderItem->save();

        $this->assertEquals($data['orderId'], $orderItem->orderId);
        $this->assertEquals($data['productId'], $orderItem->productId);
        $this->assertEquals($data['quantity'], $orderItem->quantity);
        $this->assertEquals($data['unitPrice'], $orderItem->unitPrice);
    }

    /**
     * @test
     */
    public function 注文明細を削除するとソフトデリートされること()
    {
        $orderItem = OrderItem::create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 1,
            'unitPrice' => 1000,
        ]);

        $orderItem->delete();

        $this->assertSoftDeleted($orderItem);
        $this->assertDatabaseHas('order_items', ['id' => $orderItem->id]);
    }

    /**
     * @test
     */
    public function 削除された注文明細を取得できないこと()
    {
        $orderItem = OrderItem::create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 1,
            'unitPrice' => 1000,
        ]);

        $orderItem->delete();

        $this->assertNull(OrderItem::find($orderItem->id));
    }

    /**
     * @test
     */
    public function 削除された注文明細をwithTrashedで取得できること()
    {
        $orderItem = OrderItem::create([
            'orderId' => $this->order->id,
            'productId' => $this->product->id,
            'quantity' => 1,
            'unitPrice' => 1000,
        ]);

        $orderItem->delete();

        $this->assertNotNull(OrderItem::withTrashed()->find($orderItem->id));
    }
}
