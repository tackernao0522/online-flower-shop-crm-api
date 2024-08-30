<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 管理者ユーザーを作成
        User::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'ADMIN',
        ]);

        // マネージャーユーザーを作成
        User::create([
            'username' => 'manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => 'MANAGER',
        ]);

        // スタッフユーザーを作成
        User::create([
            'username' => 'staff',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
            'role' => 'STAFF',
        ]);

        // ランダムなユーザーを20人作成
        User::factory()->count(20)->create();
    }
}
