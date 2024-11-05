<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Customer;
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private $shipmentService;
    private $user;
    private $customer;
    private $order;
    private $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーの作成（STAFFロールで作成）
        $this->user = User::factory()->staff()->create();

        // テスト用キャンペーンの作成
        Campaign::factory()->create();

        // テスト用顧客の作成
        $this->customer = Customer::factory()->create();

        // テスト用注文の作成
        $this->order = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED,
            'campaignId' => null
        ]);

        // テスト用配送情報の作成
        $this->shipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_PREPARING
        ]);

        // ShipmentServiceのモックを作成
        $this->shipmentService = $this->mock(ShipmentService::class);
    }

    /**
     * @test
     */
    public function 認証されていないユーザーが配送一覧にアクセスできないこと()
    {
        $response = $this->getJson('/api/v1/shipments');
        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function 配送一覧を正常に取得できること()
    {
        $shipments = [];
        for ($i = 0; $i < 3; $i++) {
            $order = Order::factory()->create([
                'customerId' => $this->customer->id,
                'userId' => $this->user->id
            ]);

            $shipments[] = Shipment::factory()->create([
                'orderId' => $order->id
            ]);
        }

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->getJson('/api/v1/shipments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'orderId',
                        'shippingDate',
                        'status',
                        'trackingNumber',
                        'order' => [
                            'customer'
                        ]
                    ]
                ],
                'total',
                'per_page',
                'current_page'
            ]);
    }

    /**
     * @test
     */
    public function 日付範囲で配送情報を検索できること()
    {
        $startDate = now()->subDays(7);
        $endDate = now();

        // 検索範囲内の配送情報を作成
        for ($i = 0; $i < 3; $i++) {
            $order = Order::factory()->create([
                'customerId' => $this->customer->id,
                'userId' => $this->user->id
            ]);

            Shipment::factory()->create([
                'orderId' => $order->id,
                'shippingDate' => $startDate->copy()->addDays($i)
            ]);
        }

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->getJson("/api/v1/shipments?start_date={$startDate->format('Y-m-d')}&end_date={$endDate->format('Y-m-d')}");

        $response->assertStatus(200);

        foreach ($response->json('data') as $shipment) {
            $shippingDate = Carbon::parse($shipment['shippingDate'])->startOfDay();
            $this->assertTrue(
                $shippingDate->between($startDate->startOfDay(), $endDate->endOfDay()),
                "配送日 {$shippingDate} が検索範囲外です"
            );
        }
    }

    /**
     * @test
     */
    public function 新規配送情報を正常に作成できること()
    {
        $orderWithoutShipment = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED
        ]);

        $shipmentData = [
            'orderId' => $orderWithoutShipment->id,
            'shippingDate' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => Shipment::STATUS_PREPARING,
            'trackingNumber' => '1234567890'
        ];

        $expectedShipment = Shipment::factory()->make($shipmentData);
        $expectedShipment->id = Str::uuid();
        $expectedShipment->setRelation('order', $orderWithoutShipment);

        $this->shipmentService
            ->shouldReceive('createShipment')
            ->once()
            ->with($shipmentData)
            ->andReturn($expectedShipment);

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->postJson('/api/v1/shipments', $shipmentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'orderId',
                'shippingDate',
                'status',
                'trackingNumber',
                'order' => [
                    'customer'
                ]
            ]);
    }

    /**
     * @test
     */
    public function 既に配送情報が存在する注文の場合はエラーとなること()
    {
        $shipmentData = [
            'orderId' => $this->order->id,
            'shippingDate' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => Shipment::STATUS_PREPARING
        ];

        $this->shipmentService
            ->shouldReceive('createShipment')
            ->once()
            ->with($shipmentData)
            ->andThrow(new \Exception('この注文の配送情報は既に存在します'));

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->postJson('/api/v1/shipments', $shipmentData);

        $response->assertStatus(400)
            ->assertJson(['message' => 'この注文の配送情報は既に存在します']);
    }

    /**
     * @test
     */
    public function 配送情報を正常に更新できること()
    {
        $updateData = [
            'shippingDate' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'status' => Shipment::STATUS_READY,
            'trackingNumber' => '9876543210'
        ];

        $updatedShipment = $this->shipment->replicate()->fill($updateData);
        $updatedShipment->id = $this->shipment->id;
        $updatedShipment->setRelation('order', $this->order);

        $this->shipmentService
            ->shouldReceive('UpdateShipment')
            ->once()
            ->with(\Mockery::type(Shipment::class), $updateData)
            ->andReturn($updatedShipment);

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->putJson("/api/v1/shipments/{$this->shipment->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'orderId',
                'shippingDate',
                'status',
                'trackingNumber',
                'order' => [
                    'customer'
                ]
            ]);
    }

    /**
     * @test
     */
    public function 配達完了した配送情報のステータスは変更できないこと()
    {
        $deliveredOrder = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'status' => Order::STATUS_DELIVERED,
            'campaignId' => null
        ]);

        $deliveredShipment = Shipment::factory()->create([
            'orderId' => $deliveredOrder->id,
            'status' => Shipment::STATUS_DELIVERED
        ]);

        $updateData = [
            'status' => Shipment::STATUS_IN_TRANSIT
        ];

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->putJson("/api/v1/shipments/{$deliveredShipment->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * @test
     */
    public function 配送処理が開始された配送情報は削除できないこと()
    {
        $shippedShipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_SHIPPED
        ]);

        $this->shipmentService
            ->shouldReceive('deleteShipment')
            ->once()
            ->with(\Mockery::type(Shipment::class))
            ->andThrow(new \Exception('配送処理が開始された配送情報は削除できません'));

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->deleteJson("/api/v1/shipments/{$shippedShipment->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '配送処理が開始された配送情報は削除できません']);
    }

    /**
     * @test
     */
    public function 配送準備中の配送情報を削除できること()
    {
        $preparingShipment = Shipment::factory()->create([
            'orderId' => $this->order->id,
            'status' => Shipment::STATUS_PREPARING
        ]);

        $this->shipmentService
            ->shouldReceive('deleteShipment')
            ->once()
            ->with(\Mockery::type(Shipment::class))
            ->andReturnUsing(function ($shipment) {
                $shipment->delete();
                return null;
            });

        $response = $this->withHeaders($this->actingAsUser($this->user))
            ->deleteJson("/api/v1/shipments/{$preparingShipment->id}");

        $response->assertStatus(204);

        // ソフトデリートされたことを確認
        $this->assertSoftDeleted('shipments', [
            'id' => $preparingShipment->id
        ]);
    }

    /**
     * 認証ヘッダーを生成
     */
    protected function actingAsUser($user)
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => 'Bearer ' . $token];
    }
}
