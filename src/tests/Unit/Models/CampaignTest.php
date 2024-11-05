<?php

namespace Tests\Unit\Models;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;
    private Customer $customer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーを作成
        $this->user = User::factory()->staff()->create();

        // テスト用の顧客を作成
        $this->customer = Customer::factory()->create();

        // テスト用のキャンペーンを作成
        $this->campaign = new Campaign([
            'name' => 'テストキャンペーン',
            'startDate' => now()->subDays(1),
            'endDate' => now()->addDays(5),
            'discountRate' => 10,
            'discountCode' => 'TEST10',
            'description' => 'テストキャンペーンの説明',
            'is_active' => true
        ]);
        $this->campaign->save();
    }

    /**
     * @test
     */
    public function キャンペーンから関連する注文を取得できること()
    {
        // テスト用の注文を作成
        $order1 = new Order([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED,
            'orderNumber' => 'TEST-' . now()->format('Ymd') . '-0001',
            'orderDate' => now(),
            'totalAmount' => 1000,
            'discountApplied' => 100,
            'campaignId' => $this->campaign->id
        ]);
        $order1->save();

        $order2 = new Order([
            'customerId' => $this->customer->id,
            'userId' => $this->user->id,
            'status' => Order::STATUS_CONFIRMED,
            'orderNumber' => 'TEST-' . now()->format('Ymd') . '-0002',
            'orderDate' => now(),
            'totalAmount' => 2000,
            'discountApplied' => 200,
            'campaignId' => $this->campaign->id
        ]);
        $order2->save();

        $relatedOrders = $this->campaign->orders;

        $this->assertCount(2, $relatedOrders);
        $this->assertTrue($relatedOrders->contains($order1));
        $this->assertTrue($relatedOrders->contains($order2));
    }

    /**
     * @test
     */
    public function アクティブで有効期間内のキャンペーンが有効と判定されること()
    {
        $this->assertTrue($this->campaign->isValid());
    }

    /**
     * @test
     */
    public function 非アクティブなキャンペーンが無効と判定されること()
    {
        $this->campaign->is_active = false;
        $this->campaign->save();

        $this->assertFalse($this->campaign->isValid());
    }

    /**
     * @test
     */
    public function 開始日前のキャンペーンが無効と判定されること()
    {
        $this->campaign->startDate = now()->addDays(1);
        $this->campaign->save();

        $this->assertFalse($this->campaign->isValid());
    }

    /**
     * @test
     */
    public function 終了日後のキャンペーンが無効と判定されること()
    {
        $this->campaign->endDate = now()->subDays(1);
        $this->campaign->save();

        $this->assertFalse($this->campaign->isValid());
    }

    /**
     * @test
     */
    public function 日付範囲でキャンペーンを絞り込めること()
    {
        // 現在の日付を基準に設定
        $baseDate = now();

        // 過去のキャンペーンを作成
        Campaign::factory()->create([
            'startDate' => $baseDate->copy()->subDays(20),
            'endDate' => $baseDate->copy()->subDays(15),
            'name' => '過去のキャンペーン'
        ]);

        // 未来のキャンペーンを作成
        Campaign::factory()->create([
            'startDate' => $baseDate->copy()->addDays(15),
            'endDate' => $baseDate->copy()->addDays(20),
            'name' => '未来のキャンペーン'
        ]);

        // 検索対象のキャンペーン期間を設定
        $this->campaign->update([
            'startDate' => $baseDate->copy()->subDays(5),
            'endDate' => $baseDate->copy()->addDays(5)
        ]);

        // 検索期間を設定
        $searchStart = $baseDate->copy()->subDays(10);
        $searchEnd = $baseDate->copy()->addDays(10);

        $results = Campaign::query()
            ->dateRange($searchStart, $searchEnd)
            ->get();

        // デバッグ情報の出力
        dump([
            'search_period' => [
                'start' => $searchStart->toDateString(),
                'end' => $searchEnd->toDateString()
            ],
            'target_campaign' => [
                'start' => $this->campaign->startDate->toDateString(),
                'end' => $this->campaign->endDate->toDateString()
            ],
            'found_campaigns' => $results->map(fn($c) => [
                'name' => $c->name,
                'start' => $c->startDate->toDateString(),
                'end' => $c->endDate->toDateString()
            ])->toArray()
        ]);

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->campaign));
    }

    /**
     * @test
     */
    public function キャンペーン名で検索できること()
    {
        Campaign::factory()->create(['name' => '春の特別セール']);

        $results = Campaign::nameLike('テスト')->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->campaign));
    }

    /**
     * @test
     */
    public function 割引コードで検索できること()
    {
        Campaign::factory()->create(['discountCode' => 'SPRING20']);

        $results = Campaign::discountCode('TEST10')->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->campaign));
    }

    /**
     * @test
     */
    public function 割引率の範囲で絞り込めること()
    {
        Campaign::factory()->create(['discountRate' => 5]);
        Campaign::factory()->create(['discountRate' => 20]);

        $results = Campaign::discountRateRange(8, 15)->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->campaign));
    }

    /**
     * @test
     */
    public function アクティブ状態で絞り込めること()
    {
        Campaign::factory()->create(['is_active' => false]);

        $results = Campaign::active(true)->get();

        $this->assertTrue($results->contains($this->campaign));
        foreach ($results as $result) {
            $this->assertTrue($result->is_active);
        }
    }

    /**
     * @test
     */
    public function 現在有効なキャンペーンを取得できること()
    {
        // 無効なキャンペーンを作成
        Campaign::factory()->create([
            'startDate' => now()->subDays(10),
            'endDate' => now()->subDays(5),
            'is_active' => true
        ]);

        Campaign::factory()->create([
            'startDate' => now()->subDays(1),
            'endDate' => now()->addDays(5),
            'is_active' => false
        ]);

        $results = Campaign::currentlyValid()->get();

        $this->assertEquals(1, $results->count());
        $this->assertTrue($results->contains($this->campaign));
    }

    /**
     * @test
     */
    public function 日付型が正しくキャストされること()
    {
        $campaign = new Campaign([
            'name' => 'キャスト確認キャンペーン',
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-31',
            'discountRate' => 15,
            'is_active' => true
        ]);

        $this->assertInstanceOf(Carbon::class, $campaign->startDate);
        $this->assertInstanceOf(Carbon::class, $campaign->endDate);
        $this->assertEquals('2024-01-01', $campaign->startDate->toDateString());
        $this->assertEquals('2024-01-31', $campaign->endDate->toDateString());
    }

    /**
     * @test
     */
    public function 数値型が正しくキャストされること()
    {
        $campaign = new Campaign([
            'name' => 'キャスト確認キャンペーン',
            'discountRate' => '15',
            'is_active' => '1'
        ]);

        $this->assertIsInt($campaign->discountRate);
        $this->assertEquals(15, $campaign->discountRate);
        $this->assertIsBool($campaign->is_active);
        $this->assertTrue($campaign->is_active);
    }

    /**
     * @test
     */
    public function キャンペーンを削除するとソフトデリートされること()
    {
        $this->campaign->delete();

        $this->assertSoftDeleted($this->campaign);
        $this->assertDatabaseHas('campaigns', ['id' => $this->campaign->id]);
    }

    /**
     * @test
     */
    public function 削除されたキャンペーンを取得できないこと()
    {
        $this->campaign->delete();

        $this->assertNull(Campaign::find($this->campaign->id));
    }

    /**
     * @test
     */
    public function 削除されたキャンペーンをwithTrashedで取得できること()
    {
        $this->campaign->delete();

        $this->assertNotNull(Campaign::withTrashed()->find($this->campaign->id));
    }
}
