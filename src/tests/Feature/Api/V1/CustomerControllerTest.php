<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\User;
use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $manager;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->staff = User::factory()->staff()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function actingAsUser($user)
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * @test
     * 管理者が顧客一覧を取得できること
     */
    function 管理者が顧客一覧を取得できること()
    {
        Customer::factory()->count(5)->create();

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'phoneNumber', 'address']
                ],
                'meta' => ['currentPage', 'totalPages', 'totalCount']
            ]);
    }

    /**
     * @test
     * マネージャーが顧客一覧を取得できること
     */
    function マネージャーが顧客一覧を取得できること()
    {
        Customer::factory()->count(5)->create();

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'phoneNumber', 'address']
                ],
                'meta' => ['currentPage', 'totalPages', 'totalCount']
            ]);
    }

    /**
     * @test
     * スタッフが顧客一覧を取得できること
     */
    function スタッフが顧客一覧を取得できること()
    {
        Customer::factory()->count(5)->create();

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'phoneNumber', 'address']
                ],
                'meta' => ['currentPage', 'totalPages', 'totalCount']
            ]);
    }

    /**
     * @test
     * 管理者が新規顧客を作成できること
     */
    function 管理者が新規顧客を作成できること()
    {
        $customerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phoneNumber' => '090-1234-5678',
            'address' => '123 Main St',
            'birthDate' => '1990-01-01',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email']);

        $customerData['birthDate'] = '1990-01-01 00:00:00';
        $this->assertDatabaseHas('customers', $customerData);
    }

    /**
     * @test
     * マネージャーが新規顧客を作成できること
     */
    function マネージャーが新規顧客を作成できること()
    {
        $customerData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phoneNumber' => '080-1234-5678',
            'address' => '456 Elm St',
            'birthDate' => '1985-05-15',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email']);

        $customerData['birthDate'] = '1985-05-15 00:00:00';
        $this->assertDatabaseHas('customers', $customerData);
    }

    /**
     * @test
     * スタッフが新規顧客を作成できること
     */
    function スタッフが新規顧客を作成できること()
    {
        $customerData = [
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'phoneNumber' => '070-9876-5432',
            'address' => '789 Oak St',
            'birthDate' => '1992-12-31',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email']);

        $customerData['birthDate'] = '1992-12-31 00:00:00';
        $this->assertDatabaseHas('customers', $customerData);
    }

    /**
     * @test
     * 管理者が顧客情報を更新できること
     */
    function 管理者が顧客情報を更新できること()
    {
        $customer = Customer::factory()->create();
        $updateData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phoneNumber' => '090-4321-1234',
            'address' => '456 Elm St',
            'birthDate' => '1985-05-15',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->putJson("/api/v1/customers/{$customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson($updateData);

        $updateData['birthDate'] = '1985-05-15 00:00:00';
        $this->assertDatabaseHas('customers', $updateData);
    }

    /**
     * @test
     * 顧客作成時にデータベースエラーが発生した場合500エラーが返されること
     */
    function 顧客作成時にデータベースエラーが発生した場合500エラーが返されること()
    {
        DB::shouldReceive('beginTransaction')
            ->andThrow(new QueryException('mysql', 'INSERT INTO customers', [], new \Exception('Database error')));

        $customerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phoneNumber' => '090-1234-5678',
            'address' => '123 Main St',
            'birthDate' => '1990-01-01',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(500)
            ->assertJson([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'ユーザー操作に失敗しました。',
                ],
            ]);
    }

    /**
     * @test
     * 管理者が顧客を削除できること
     */
    function 管理者が顧客を削除できること()
    {
        $customer = Customer::factory()->create();

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /**
     * @test
     * 顧客削除時にデータベースエラーが発生した場合500エラーが返されること
     */
    function 顧客削除時にデータベースエラーが発生した場合500エラーが返されること()
    {
        $customer = Customer::factory()->create();

        // モックを使用して、顧客の削除処理でエラーを発生させる
        $this->mock(Customer::class, function ($mock) {
            $mock->shouldReceive('delete')
                ->andThrow(new QueryException('mysql', 'DELETE FROM customers WHERE id = ?', [], new \Exception('Database error')));
        });

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->deleteJson("/api/v1/customers/{$customer->id}");

        // 正しいステータスコード500が返されることを確認
        $response->assertStatus(500)
            ->assertJson([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'ユーザー操作に失敗しました。',
                ],
            ]);
    }

    /**
     * @test
     * 認証されていないユーザーが顧客一覧にアクセスできないこと
     */
    function 認証されていないユーザーが顧客一覧にアクセスできないこと()
    {
        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /**
     * @test
     * 存在しない顧客情報にアクセスすると404エラーが返されること
     */
    function 存在しない顧客情報にアクセスすると404エラーが返されること()
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson("/api/v1/customers/{$nonExistentId}");

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => '指定されたリソースが見つかりません。'
                ]
            ]);
    }

    /**
     * @test
     * 無効なデータで顧客を作成しようとするとバリデーションエラーが返されること
     */
    function 無効なデータで顧客を作成しようとするとバリデーションエラーが返されること()
    {
        $invalidData = [
            'name' => '',
            'email' => 'not-an-email',
            'phoneNumber' => 'abc',
            'address' => '',
            'birthDate' => 'invalid-date',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->postJson('/api/v1/customers', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phoneNumber', 'address', 'birthDate']);
    }

    /**
     * @test
     * 予期せぬ例外が発生した場合に500エラーが返されること
     */
    public function 予期せぬ例外が発生した場合に500エラーが返されること()
    {
        $this->mock(CustomerController::class, function ($mock) {
            $mock->shouldReceive('index')->once()->andThrow(new \Exception('Unexpected error'));
            $mock->shouldReceive('getMiddleware')->andReturn([]);
            $mock->shouldReceive('callAction')->andReturnUsing(function ($method, $parameters) use ($mock) {
                return $mock->$method(...$parameters);
            });
        });

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson('/api/v1/customers');

        $response->assertStatus(500)
            ->assertJson([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'ユーザー操作に失敗しました。',
                ]
            ]);
    }

    /**
     * @test
     * 顧客情報の更新時にバリデーションエラーが発生すること
     */
    function 顧客情報の更新時にバリデーションエラーが発生すること()
    {
        $customer = Customer::factory()->create();
        $invalidData = [
            'email' => 'not-an-email',
            'phoneNumber' => 'invalid-phone',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->putJson("/api/v1/customers/{$customer->id}", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phoneNumber']);
    }

    /**
     * @test
     * 存在しない顧客の削除を試みると404エラーが返されること
     */
    function 存在しない顧客の削除を試みると404エラーが返されること()
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->deleteJson("/api/v1/customers/{$nonExistentId}");

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => '指定されたリソースが見つかりません。'
                ]
            ]);
    }
}
