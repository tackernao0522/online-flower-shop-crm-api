<?php

namespace Tests\Unit\Models;

use App\Models\Shipment;
use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private Customer $customer;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーを作成
        $user = User::factory()->staff()->create();

        // テスト用の顧客を作成
        $this->customer = Customer::factory()->create([
            'name' => 'テスト顧客'
        ]);

        // テスト用の注文を作成
        $this->order = new Order([
            'customerId' => $this->customer->id,
            'userId' => $user->id,
            'status' => Order::STATUS_CONFIRMED,
            'orderNumber' => 'TEST-' . now()->format('Ymd') . '-0001',
            'orderDate' => now(),
            'totalAmount' => 1000,
            'discountApplied' => 0
        ]);
        $this->order->save();

        // テスト用の配送情報を作成
        $this->shipment = new Shipment([
            'orderId' => $this->order->id,
            'shippingDate' => now(),
            'status' => Shipment::STATUS_PREPARING,
            'trackingNumber' => '1234567890',
            'deliveryNote' => 'テスト配送メモ'
        ]);
        $this->shipment->save();
    }

    /**
     * @test
     */
    public function すべての配送ステータスが定義されていること()
    {
        $statuses = Shipment::getAvailableStatuses();

        $this->assertEquals([
            Shipment::STATUS_PREPARING,
            Shipment::STATUS_READY,
            Shipment::STATUS_SHIPPED,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_DELIVERED,
            Shipment::STATUS_FAILED,
            Shipment::STATUS_RETURNED
        ], $statuses);
    }

    /**
     * @test
     */
    public function 配送情報から関連する注文を取得できること()
    {
        $relatedOrder = $this->shipment->order;

        $this->assertInstanceOf(Order::class, $relatedOrder);
        $this->assertEquals($this->order->id, $relatedOrder->id);
        $this->assertEquals($this->order->orderNumber, $relatedOrder->orderNumber);
    }

    /**
     * @test
     */
    public function 日付範囲で配送情報を絞り込めること()
    {
        // 過去の配送情報を作成
        Shipment::factory()->create([
            'orderId' => $this->order->id,
            'shippingDate' => now()->subDays(5),
        ]);

        // 未来の配送情報を作成
        Shipment::factory()->create([
            'orderId' => $this->order->id,
            'shippingDate' => now()->addDays(5),
        ]);

        $startDate = now()->subDays(1);
        $endDate = now()->addDays(1);

        $results = Shipment::dateRange($startDate, $endDate)->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->shipment));
    }

    /**
     * @test
     */
    public function ステータスで配送情報を絞り込めること()
    {
        // 異なるステータスの配送情報を作成
        Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_DELIVERED,
        ]);

        $results = Shipment::withStatus(Shipment::STATUS_PREPARING)->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->shipment));
    }

    /**
     * @test
     */
    public function 追跡番号で配送情報を検索できること()
    {
        // 異なる追跡番号の配送情報を作成
        Shipment::factory()->create([
            'orderId' => $this->order->id,
            'trackingNumber' => '9876543210',
        ]);

        $results = Shipment::trackingNumberLike('12345')->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->shipment));
    }

    /**
     * @test
     */
    public function 注文番号で配送情報を検索できること()
    {
        // 異なる注文の配送情報を作成
        $anotherOrder = new Order([
            'customerId' => $this->customer->id,
            'userId' => $this->order->userId,
            'status' => Order::STATUS_CONFIRMED,
            'orderNumber' => 'OTHER-' . now()->format('Ymd') . '-0001',
            'orderDate' => now(),
            'totalAmount' => 1000,
            'discountApplied' => 0
        ]);
        $anotherOrder->save();

        Shipment::factory()->create([
            'orderId' => $anotherOrder->id,
        ]);

        $results = Shipment::orderNumberLike('TEST-')->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->shipment));
    }

    /**
     * @test
     */
    public function 顧客名で配送情報を検索できること()
    {
        // 異なる顧客の配送情報を作成
        $anotherCustomer = Customer::factory()->create([
            'name' => '別の顧客'
        ]);

        $anotherOrder = new Order([
            'customerId' => $anotherCustomer->id,
            'userId' => $this->order->userId,
            'status' => Order::STATUS_CONFIRMED,
            'orderNumber' => 'OTHER-' . now()->format('Ymd') . '-0001',
            'orderDate' => now(),
            'totalAmount' => 1000,
            'discountApplied' => 0
        ]);
        $anotherOrder->save();

        Shipment::factory()->create([
            'orderId' => $anotherOrder->id,
        ]);

        $results = Shipment::customerNameLike('テスト顧客')->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->shipment));
    }

    /**
     * @test
     */
    public function 配送日が日時型にキャストされること()
    {
        $shipment = new Shipment([
            'orderId' => $this->order->id,
            'shippingDate' => '2024-01-01 10:00:00',
            'status' => Shipment::STATUS_PREPARING
        ]);

        $this->assertInstanceOf(Carbon::class, $shipment->shippingDate);
        $this->assertEquals('2024-01-01 10:00:00', $shipment->shippingDate->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     */
    public function 配送情報を削除するとソフトデリートされること()
    {
        $this->shipment->delete();

        $this->assertSoftDeleted($this->shipment);
        $this->assertDatabaseHas('shipments', ['id' => $this->shipment->id]);
    }

    /**
     * @test
     */
    public function 削除された配送情報を取得できないこと()
    {
        $this->shipment->delete();

        $this->assertNull(Shipment::find($this->shipment->id));
    }

    /**
     * @test
     */
    public function 削除された配送情報をwithTrashedで取得できること()
    {
        $this->shipment->delete();

        $this->assertNotNull(Shipment::withTrashed()->find($this->shipment->id));
    }
}
