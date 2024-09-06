<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class User extends Authenticatable implements JWTSubject, AuthenticatableContract
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, MockeryPHPUnitIntegration;

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
