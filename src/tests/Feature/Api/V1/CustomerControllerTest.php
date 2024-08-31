<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\User;
use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Mockery;

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
     */
    function 管理者が新規顧客を作成できること()
    {
        $customerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phoneNumber' => '1234567890',
            'address' => '123 Main St',
            'birthDate' => '1990-01-01 00:00:00',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email']);

        $this->assertDatabaseHas('customers', $customerData);
    }

    /**
     * @test
     */
    function マネージャーが新規顧客を作成できること()
    {
        $customerData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phoneNumber' => '9876543210',
            'address' => '456 Elm St',
            'birthDate' => '1985-05-15 00:00:00',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email']);

        $this->assertDatabaseHas('customers', $customerData);
    }

    /**
     * @test
     */
    function スタッフが新規顧客を作成できること()
    {
        $customerData = [
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'phoneNumber' => '5555555555',
            'address' => '789 Oak St',
            'birthDate' => '1992-12-31 00:00:00',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email']);

        $this->assertDatabaseHas('customers', $customerData);
    }

    /**
     * @test
     */
    function 認証されていないユーザーが顧客作成を試みると401エラーが返されること()
    {
        $customerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phoneNumber' => '1234567890',
            'address' => '123 Main St',
            'birthDate' => '1990-01-01 00:00:00',
        ];

        $response = $this->postJson('/api/v1/customers', $customerData);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /**
     * @test
     */
    function 管理者が特定の顧客情報を取得できること()
    {
        $customer = Customer::factory()->create();

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson($customer->toArray());
    }

    /**
     * @test
     */
    function マネージャーが特定の顧客情報を取得できること()
    {
        $customer = Customer::factory()->create();

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson($customer->toArray());
    }

    /**
     * @test
     */
    function スタッフが特定の顧客情報を取得できること()
    {
        $customer = Customer::factory()->create();

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson($customer->toArray());
    }

    /**
     * @test
     */
    function 管理者が顧客情報を更新できること()
    {
        $customer = Customer::factory()->create();
        $updateData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phoneNumber' => '9876543210',
            'address' => '456 Elm St',
            'birthDate' => '1985-05-15 00:00:00',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->admin))
            ->putJson("/api/v1/customers/{$customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson($updateData);

        $this->assertDatabaseHas('customers', $updateData);
    }

    /**
     * @test
     */
    function マネージャーが顧客情報を更新できること()
    {
        $customer = Customer::factory()->create();
        $updateData = [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'phoneNumber' => '1231231234',
            'address' => '789 Pine St',
            'birthDate' => '1988-10-10 00:00:00',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->putJson("/api/v1/customers/{$customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson($updateData);

        $this->assertDatabaseHas('customers', $updateData);
    }

    /**
     * @test
     */
    function スタッフが顧客情報を更新できること()
    {
        $customer = Customer::factory()->create();
        $updateData = [
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'phoneNumber' => '9998887777',
            'address' => '321 Oak St',
            'birthDate' => '1995-12-25 00:00:00',
        ];

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->putJson("/api/v1/customers/{$customer->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson($updateData);

        $this->assertDatabaseHas('customers', $updateData);
    }

    /**
     * @test
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
     */
    function マネージャーが顧客を削除できること()
    {
        $customer = Customer::factory()->create();

        $response = $this->withHeaders($this->actingAsUser($this->manager))
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /**
     * @test
     */
    function スタッフが顧客を削除できること()
    {
        $customer = Customer::factory()->create();

        $response = $this->withHeaders($this->actingAsUser($this->staff))
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /**
     * @test
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
                    'message' => 'ユーザー操作に失敗しました。'
                ]
            ]);
    }

    /**
     * @test
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
