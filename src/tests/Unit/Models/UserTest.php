<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    function ユーザーが管理者かどうかを確認できる()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $nonAdmin = User::factory()->create(['role' => 'STAFF']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($nonAdmin->isAdmin());
    }

    /**
     * @test
     */
    function ユーザーがマネージャーかどうかを確認できる()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $nonManager = User::factory()->create(['role' => 'STAFF']);

        $this->assertTrue($manager->isManager());
        $this->assertFalse($nonManager->isManager());
    }

    /**
     * @test
     */
    function ユーザーがスタッフかどうかを確認できる()
    {
        $staff = User::factory()->create(['role' => 'STAFF']);
        $nonStaff = User::factory()->create(['role' => 'ADMIN']);

        $this->assertTrue($staff->isStaff());
        $this->assertFalse($nonStaff->isStaff());
    }

    /**
     * @test
     */
    function ユーザーが特定の役割を持っているかを確認できる()
    {
        $user = User::factory()->create(['role' => 'MANAGER']);

        $this->assertTrue($user->hasRole('MANAGER'));
        $this->assertFalse($user->hasRole('ADMIN'));
    }

    /**
     * @test
     */
    function JWT識別子が正しく返される()
    {
        $user = User::factory()->create();

        $this->assertEquals($user->id, $user->getJWTIdentifier());
    }

    /**
     * @test
     */
    function JWTカスタムクレームが空の配列を返す()
    {
        $user = User::factory()->create();

        $this->assertEmpty($user->getJWTCustomClaims());
    }
}
