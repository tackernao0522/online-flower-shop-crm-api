<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private   UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy();
    }

    /**
     * @test
     */
    function 管理者は全てのユーザーを閲覧できる()
    {
        $admin = User::factory()->create(['role' => "ADMIN"]);
        $this->assertTrue($this->policy->viewAny($admin));
    }

    /**
     * @test
     */
    function マネージャーは全てのユーザーを閲覧できる()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $this->assertTrue($this->policy->viewAny($manager));
    }

    /**
     * @test
     */
    function スタッフは全てのユーザーを閲覧できない()
    {
        $staff = User::factory()->create(['role' => 'STAFF']);
        $this->assertFalse($this->policy->viewAny($staff));
    }

    /**
     * @test
     */
    function ユーザーは自分のプロフィールを閲覧できる()
    {
        $user = User::factory()->create();
        $this->assertTrue($this->policy->view($user, $user));
    }

    /**
     * @test
     */
    function 管理者はユーザーを作成できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->assertTrue($this->policy->create($admin));
    }

    /**
     * @test
     */
    function 管理者は任意のユーザーを更新できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $user = User::factory()->create();
        $this->assertTrue($this->policy->update($admin, $user));
    }

    /**
     * @test
     */
    function ユーザーは自分のプロフィールを更新できる()
    {
        $user = User::factory()->create();
        $this->assertTrue($this->policy->update($user, $user));
    }

    /**
     * @test
     */
    function 管理者は他のユーザーを削除できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $user = User::factory()->create();
        $this->assertTrue($this->policy->delete($admin, $user)->allowed());
    }

    /**
     * @test
     */
    function 管理者は自分自身を削除できない()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->assertFalse($this->policy->delete($admin, $admin)->allowed());
    }

    /**
     * @test
     */
    function 管理者以外はユーザーを削除できない()
    {
        $nonAdmin = User::factory()->create(['role' => 'STAFF']);
        $user = User::factory()->create();
        $this->assertFalse($this->policy->delete($nonAdmin, $user)->allowed());
    }

    /**
     * @test
     */
    function ユーザーは自分のパスワードを変更できる()
    {
        $user = User::factory()->create();
        $this->assertTrue($this->policy->changePassword($user, $user));
    }

    /**
     * @test
     */
    function 管理者は他のユーザーのパスワードを変更できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $user = User::factory()->create();
        $this->assertTrue($this->policy->changePassword($admin, $user));
    }

    /**
     * @test
     */
    function 管理者以外は他のユーザーのパスワードを変更できない()
    {
        $staff = User::factory()->create(['role' => 'STAFF']);
        $user = User::factory()->create();
        $this->assertFalse($this->policy->changePassword($staff, $user));
    }

    /**
     * @test
     */
    function 管理者は削除されたユーザーを復元できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $user = User::factory()->create();
        $this->assertTrue($this->policy->restore($admin, $user)->allowed());
    }

    /**
     * @test
     */
    function 管理者以外は削除されたユーザーを復元できない()
    {
        $staff = User::factory()->create(['role' => 'STAFF']);
        $user = User::factory()->create();
        $this->assertFalse($this->policy->restore($staff, $user)->allowed());
    }

    /**
     * @test
     */
    function 管理者は他のユーザーを完全に削除できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $user = User::factory()->create();
        $this->assertTrue($this->policy->forceDelete($admin, $user));
    }


    /**
     * @test
     */
    function 管理者は自分自身を完全に削除できない()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->assertFalse($this->policy->forceDelete($admin, $admin));
    }

    /**
     * @test
     */
    function 管理者は他のユーザーの役割を変更できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $user = User::factory()->create();
        $this->assertTrue($this->policy->changeRole($admin, $user));
    }

    /**
     * @test
     */
    function 管理者は自分自身の役割を変更できない()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->assertFalse($this->policy->changeRole($admin, $admin));
    }

    /**
     * @test
     */
    function 管理者以外はユーザーの役割を変更できない()
    {
        $staff = User::factory()->create(['role' => 'STAFF']);
        $user = User::factory()->create();
        $this->assertFalse($this->policy->changeRole($staff, $user));
    }
}
