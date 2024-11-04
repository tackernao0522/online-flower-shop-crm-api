<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campaign;
use App\Models\Order;
use App\Models\User;
use App\Models\Customer;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    private $campaignService;
    private $admin;
    private $manager;
    private $staff;
    private $activeCampaign;
    private $pastCampaign;
    private $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーの作成
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->staff = User::factory()->staff()->create();

        // テスト用顧客の作成
        $this->customer = Customer::factory()->create();

        $today = Carbon::today();

        // アクティブなキャンペーンを作成
        $this->activeCampaign = Campaign::create([
            'name' => '母の日特別キャンペーン',
            'startDate' => $today,
            'endDate' => $today->copy()->addDays(13),
            'discountRate' => 20,
            'discountCode' => 'MOTHER2024',
            'description' => 'テストキャンペーン',
            'is_active' => true,
        ]);

        // 過去のキャンペーンを作成
        $this->pastCampaign = Campaign::create([
            'name' => '過去のキャンペーン',
            'startDate' => $today->copy()->subMonths(2),
            'endDate' => $today->copy()->subMonths(1),
            'discountRate' => 15,
            'discountCode' => 'PAST2024',
            'description' => '過去のテストキャンペーン',
            'is_active' => false,
        ]);

        $this->campaignService = Mockery::mock(CampaignService::class);
        $this->app->instance(CampaignService::class, $this->campaignService);
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
     */
    public function 認証されていないユーザーがキャンペーン一覧にアクセスできないこと()
    {
        $response = $this->getJson('/api/v1/campaigns');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * @test
     */
    public function キャンペーン一覧を正常に取得できること()
    {
        // テストデータの作成前にデータベースをクリーンに
        Campaign::query()->delete();

        $today = Carbon::today();

        // テストデータを作成（一意のdiscountCodeを使用）
        Campaign::create([
            'name' => '母の日特別キャンペーン',
            'startDate' => $today,
            'endDate' => $today->copy()->addDays(13),
            'discountRate' => 20,
            'discountCode' => 'MOTHER2024_1',
            'description' => 'テストキャンペーン',
            'is_active' => true,
        ]);

        Campaign::create([
            'name' => '過去のキャンペーン',
            'startDate' => $today->copy()->subMonths(2),
            'endDate' => $today->copy()->subMonths(1),
            'discountRate' => 15,
            'discountCode' => 'PAST2024_1',
            'description' => '過去のテストキャンペーン',
            'is_active' => false,
        ]);

        // 追加のキャンペーンを作成（一意のdiscountCode）
        for ($i = 1; $i <= 3; $i++) {
            Campaign::create([
                'name' => "テストキャンペーン{$i}",
                'startDate' => $today->copy()->addDays($i),
                'endDate' => $today->copy()->addDays($i + 14),
                'discountRate' => 10 + $i,
                'discountCode' => "TEST{$i}2024_1",
                'description' => "テストキャンペーン{$i}の説明",
                'is_active' => true,
            ]);
        }

        // データベースの状態を確認
        $allCampaigns = Campaign::all();
        Log::info('All campaigns in database:', [
            'count' => $allCampaigns->count(),
            'campaigns' => $allCampaigns->toArray()
        ]);

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson('/api/v1/campaigns');

        Log::info('Response data:', $response->json());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'startDate',
                        'endDate',
                        'discountRate',
                        'discountCode',
                        'is_active'
                    ]
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(5, $data, sprintf(
            'Expected 5 campaigns, but got %d. Database has %d campaigns. Response: %s',
            count($data),
            $allCampaigns->count(),
            json_encode($response->json(), JSON_PRETTY_PRINT)
        ));
    }

    /**
     * @test
     */
    public function 現在有効なキャンペーンのみを取得できること()
    {
        Log::info('Active campaign data:', $this->activeCampaign->toArray());

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson('/api/v1/campaigns?current_only=1&is_active=1');

        Log::info('Response data for active campaigns:', $response->json());

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data, 'No active campaigns found in response');
        $this->assertTrue(
            collect($data)->contains(function ($campaign) {
                return $campaign['name'] === '母の日特別キャンペーン';
            }),
            'Active campaign not found in response'
        );
    }

    /**
     * @test
     */
    public function キャンペーンを日付範囲で検索できること()
    {
        $today = Carbon::today();

        $params = [
            'start_date' => $today->format('Y-m-d'),
            'end_date' => $today->copy()->addDays(13)->format('Y-m-d'),
            'is_active' => 1
        ];

        // 指定した日付範囲内のキャンペーンを確認
        $campaignsInRange = Campaign::query()
            ->dateRange($params['start_date'], $params['end_date'])
            ->active(true)
            ->get();

        Log::info('Campaigns in date range before request:', [
            'params' => $params,
            'campaigns' => $campaignsInRange->toArray()
        ]);

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson('/api/v1/campaigns?' . http_build_query($params));

        Log::info('Response data for date range:', $response->json());

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data, 'No campaigns found in date range');
        $this->assertTrue(
            collect($data)->contains(function ($campaign) {
                return $campaign['name'] === '母の日特別キャンペーン';
            }),
            'Expected campaign not found in date range'
        );
    }

    /**
     * @test
     */
    public function 新規キャンペーンを作成できること()
    {
        $campaignData = [
            'name' => 'サマーセール2024',
            'startDate' => now()->addDays(5)->format('Y-m-d'),
            'endDate' => now()->addDays(19)->format('Y-m-d'),
            'discountRate' => 25,
            'discountCode' => 'SUMMER2024',
            'description' => '夏の特別セール',
            'is_active' => true
        ];

        $this->campaignService
            ->shouldReceive('createCampaign')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(new Campaign($campaignData));

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->postJson('/api/v1/campaigns', $campaignData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'name',
                'startDate',
                'endDate',
                'discountRate',
                'discountCode',
                'is_active'
            ])
            ->assertJsonPath('name', 'サマーセール2024');
    }

    /**
     * @test
     */
    public function 利用実績のあるキャンペーンの割引率は変更できないこと()
    {
        // キャンペーンを使用した注文を作成
        Order::factory()->create([
            'campaignId' => $this->activeCampaign->id,
            'discountApplied' => 1000
        ]);

        $updateData = [
            'name' => '更新後のキャンペーン',
            'discountRate' => 30 // 変更しようとする
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->putJson("/api/v1/campaigns/{$this->activeCampaign->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discountRate']);
    }

    /**
     * @test
     */
    public function キャンペーンの詳細情報を取得できること()
    {
        // 注文データを作成
        Order::factory()->count(2)->create([
            'campaignId' => $this->activeCampaign->id,
            'discountApplied' => 1000
        ]);

        $campaignDetails = array_merge($this->activeCampaign->toArray(), [
            'isCurrentlyActive' => true,
            'ordersCount' => 2,
            'totalDiscountAmount' => 2000
        ]);

        $this->campaignService
            ->shouldReceive('getCampaignDetails')
            ->once()
            ->with(Mockery::type(Campaign::class))
            ->andReturn($campaignDetails);

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson("/api/v1/campaigns/{$this->activeCampaign->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'isCurrentlyActive',
                'ordersCount',
                'totalDiscountAmount'
            ])
            ->assertJsonPath('ordersCount', 2)
            ->assertJsonPath('totalDiscountAmount', 2000);
    }

    /**
     * @test
     */
    public function 使用中のキャンペーンは削除できないこと()
    {
        // キャンペーンを使用した注文を作成
        Order::factory()->create([
            'campaignId' => $this->activeCampaign->id
        ]);

        $this->campaignService
            ->shouldReceive('deleteCampaign')
            ->once()
            ->with(Mockery::type(Campaign::class))
            ->andThrow(new \Exception('使用中のキャンペーンは削除できません'));

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->deleteJson("/api/v1/campaigns/{$this->activeCampaign->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '使用中のキャンペーンは削除できません']);
    }

    /**
     * @test
     */
    public function 使用されていないキャンペーンは削除できること()
    {
        $unusedCampaign = Campaign::factory()->create();

        $this->campaignService
            ->shouldReceive('deleteCampaign')
            ->once()
            ->with(Mockery::type(Campaign::class))
            ->andReturnNull();

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->deleteJson("/api/v1/campaigns/{$unusedCampaign->id}");

        $response->assertStatus(204);
    }

    /**
     * @test
     */
    public function キャンペーン作成時の割引コードは一意である必要があること()
    {
        $campaignData = [
            'name' => '新規キャンペーン',
            'startDate' => now()->addDay()->format('Y-m-d'),
            'endDate' => now()->addDays(14)->format('Y-m-d'),
            'discountRate' => 20,
            'discountCode' => 'MOTHER2024', // 既存のコードを使用
            'is_active' => true
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->postJson('/api/v1/campaigns', $campaignData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discountCode']);
    }

    /**
     * @test
     */
    public function キャンペーンのステータスを切り替えられること()
    {
        $this->campaignService
            ->shouldReceive('toggleStatus')
            ->once()
            ->with(Mockery::type(Campaign::class))
            ->andReturn(new Campaign(array_merge(
                $this->activeCampaign->toArray(),
                ['is_active' => false]
            )));

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->putJson("/api/v1/campaigns/{$this->activeCampaign->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonPath('is_active', false);
    }
}
