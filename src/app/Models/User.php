<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class User extends Authenticatable implements JWTSubject, AuthenticatableContract
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    // 開発環境とテスト環境でのみMockeryトレイトを使用
    public function initializeMockery()
    {
        if (app()->environment('local', 'testing')) {
            $this->initializeMockeryPHPUnitIntegration();
        }
    }

    protected function initializeMockeryPHPUnitIntegration()
    {
        $this->mockeryPHPUnitIntegration = trait_exists('\Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration')
            ? \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration::class
            : null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'last_login_date',
        'is_online',
        'last_activity',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'role' => 'string',
        'is_active' => 'boolean',
    ];

    public function isAdmin()
    {
        return $this->role === 'ADMIN';
    }

    public function isManager()
    {
        return $this->role === 'MANAGER';
    }

    public function isStaff()
    {
        return $this->role === 'STAFF';
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
