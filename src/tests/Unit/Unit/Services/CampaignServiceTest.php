<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignServiceTest extends TestCase
{
    use RefreshDatabase;

    private CampaignService $campaignService;
    private Campaign $campaign;
    private User $user;
    private Customer $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignService = new CampaignService();

        // テストユーザーを作成
        $this->user = User::factory()->staff()->create();

        // テスト用の顧客を作成
        $this->customer = Customer::factory()->create();

        // テスト用の商品を作成
        $this->product = Product::factory()->create([
            'price' => 1000,
            'stockQuantity' => 100,
        ]);

        // テスト用の基本キャンペーンを作成
        $this->campaign = Campaign::factory()->create([
            'name' => 'テストキャンペーン',
            'description' => 'テスト用のキャンペーンです',
            'startDate' => now()->subDay(),
            'endDate' => now()->addDays(7),
            'discountRate' => 10,
            'is_active' => true,
        ]);
    }

    /**
     * @test
     */
    public function キャンペーン詳細情報を正しく取得できること()
    {
        // 注文を作成
        $order = Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'campaignId' => $this->campaign->id,
            'totalAmount' => 9000,
            'discountApplied' => 1000,
        ]);

        $details = $this->campaignService->getCampaignDetails($this->campaign);

        $this->assertEquals($this->campaign->name, $details['name']);
        $this->assertEquals($this->campaign->description, $details['description']);
        $this->assertTrue($details['isCurrentlyActive']);
        $this->assertEquals(1, $details['ordersCount']);
        $this->assertEquals(1000, $details['totalDiscountAmount']);
    }

    /**
     * @test
     */
    public function 複数の注文がある場合の総割引額が正しく計算されること()
    {
        // 複数の注文を作成
        Order::factory()->count(3)->create([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'campaignId' => $this->campaign->id,
            'totalAmount' => 9000,
            'discountApplied' => 1000,
        ]);

        $details = $this->campaignService->getCampaignDetails($this->campaign);

        $this->assertEquals(3, $details['ordersCount']);
        $this->assertEquals(3000, $details['totalDiscountAmount']); // 1000 × 3
    }

    /**
     * @test
     */
    public function 新規キャンペーンを作成できること()
    {
        $campaignData = [
            'name' => '新規テストキャンペーン',
            'description' => '新規作成テスト用のキャンペーンです',
            'startDate' => now()->addDay(),
            'endDate' => now()->addDays(14),
            'discountRate' => 20,
            'discountCode' => 'TEST20',
            'is_active' => true,
        ];

        $campaign = $this->campaignService->createCampaign($campaignData);

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals($campaignData['name'], $campaign->name);
        $this->assertEquals($campaignData['discountRate'], $campaign->discountRate);
        $this->assertEquals($campaignData['discountCode'], $campaign->discountCode);
        $this->assertTrue($campaign->is_active);
    }

    /**
     * @test
     */
    public function キャンペーン情報を更新できること()
    {
        $updateData = [
            'name' => '更新後のキャンペーン',
            'description' => '更新後の説明文',
            'discountRate' => 15,
        ];

        $updatedCampaign = $this->campaignService->updateCampaign($this->campaign, $updateData);

        $this->assertEquals($updateData['name'], $updatedCampaign->name);
        $this->assertEquals($updateData['description'], $updatedCampaign->description);
        $this->assertEquals($updateData['discountRate'], $updatedCampaign->discountRate);
    }

    /**
     * @test
     */
    public function 使用されていないキャンペーンを削除できること()
    {
        $campaign = Campaign::factory()->create();

        $this->campaignService->deleteCampaign($campaign);

        $this->assertSoftDeleted($campaign);
    }

    /**
     * @test
     */
    public function 使用中のキャンペーンは削除できないこと()
    {
        // キャンペーンを使用する注文を作成
        Order::factory()->create([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'campaignId' => $this->campaign->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('使用中のキャンペーンは削除できません');

        $this->campaignService->deleteCampaign($this->campaign);
    }

    /**
     * @test
     */
    public function キャンペーンのステータスを有効から無効に切り替えできること()
    {
        $this->campaign->is_active = true;
        $this->campaign->save();

        $updatedCampaign = $this->campaignService->toggleStatus($this->campaign);

        $this->assertFalse($updatedCampaign->is_active);
    }

    /**
     * @test
     */
    public function キャンペーンのステータスを無効から有効に切り替えできること()
    {
        $this->campaign->is_active = false;
        $this->campaign->save();

        $updatedCampaign = $this->campaignService->toggleStatus($this->campaign);

        $this->assertTrue($updatedCampaign->is_active);
    }
}
