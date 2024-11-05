<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShipmentService $shipmentService;
    private Order $order;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shipmentService = new ShipmentService();

        // テストユーザーを作成
        $this->user = User::factory()->staff()->create();

        // テスト用の顧客を作成
        $this->customer = Customer::factory()->create();

        // テスト用の注文を作成（ファクトリーの代わりに直接作成）
        $this->order = new Order([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED,
            'orderNumber' => 'TEST-' . now()->format('Ymd') . '-0001',
            'orderDate' => now(),
            'totalAmount' => 1000,
            'discountApplied' => 0
        ]);
        $this->order->save();
    }

    /**
     * @test
     */
    public function 基本的な配送情報を作成できること()
    {
        $shipmentData = [
            'orderId' => $this->order->id,
            'shippingDate' => now()->addDays(2),
            'status' => Shipment::STATUS_PREPARING,
            'trackingNumber' => '1234567890',
            'deliveryNote' => '配送備考'
        ];

        $shipment = $this->shipmentService->createShipment($shipmentData);

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertEquals($this->order->id, $shipment->orderId);
        $this->assertEquals(Shipment::STATUS_PREPARING, $shipment->status);
        $this->assertEquals('1234567890', $shipment->trackingNumber);
        $this->assertEquals('配送備考', $shipment->deliveryNote);
    }

    /**
     * @test
     */
    public function 同じ注文に対して複数の配送情報を作成できないこと()
    {
        // 最初の配送情報を作成
        Shipment::factory()->create([
            'orderId' => $this->order->id
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('この注文の配送情報は既に存在します');

        // 同じ注文IDで新しい配送情報を作成
        $this->shipmentService->createShipment([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_PREPARING
        ]);
    }

    /**
     * @test
     */
    public function 配送情報を更新できること()
    {
        $shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_PREPARING
        ]);

        $updateData = [
            'status' => Shipment::STATUS_SHIPPED,
            'trackingNumber' => '9876543210',
            'deliveryNote' => '更新された配送備考'
        ];

        $updatedShipment = $this->shipmentService->updateShipment($shipment, $updateData);

        $this->assertEquals(Shipment::STATUS_SHIPPED, $updatedShipment->status);
        $this->assertEquals('9876543210', $updatedShipment->trackingNumber);
        $this->assertEquals('更新された配送備考', $updatedShipment->deliveryNote);
    }

    /**
     * @test
     */
    public function 配送ステータスが発送済みに更新されたとき注文ステータスも更新されること()
    {
        $shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_PREPARING
        ]);

        $this->shipmentService->updateShipment($shipment, [
            'status' => Shipment::STATUS_SHIPPED
        ]);

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_SHIPPED, $this->order->status);
    }

    /**
     * @test
     */
    public function 配送ステータスが配達完了に更新されたとき注文ステータスも更新されること()
    {
        $shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_SHIPPED
        ]);

        $this->shipmentService->updateShipment($shipment, [
            'status' => Shipment::STATUS_DELIVERED
        ]);

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $this->order->status);
    }

    /**
     * @test
     */
    public function 準備中の配送情報を削除できること()
    {
        $shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_PREPARING
        ]);

        $this->shipmentService->deleteShipment($shipment);

        $this->assertSoftDeleted($shipment);
    }

    /**
     * @test
     */
    public function 発送済みの配送情報は削除できないこと()
    {
        $shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_SHIPPED
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('配送処理が開始された配送情報は削除できません');

        $this->shipmentService->deleteShipment($shipment);
    }

    /**
     * @test
     */
    public function 配達中の配送情報は削除できないこと()
    {
        $shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_IN_TRANSIT
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('配送処理が開始された配送情報は削除できません');

        $this->shipmentService->deleteShipment($shipment);
    }

    /**
     * @test
     */
    public function 配達完了の配送情報は削除できないこと()
    {
        $shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_DELIVERED
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('配送処理が開始された配送情報は削除できません');

        $this->shipmentService->deleteShipment($shipment);
    }
}
