<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 管理者、マネージャー、スタッフの固定ユーザーを作成
        User::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'ADMIN',
        ]);

        User::create([
            'username' => 'manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => 'MANAGER',
        ]);

        User::create([
            'username' => 'staff',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
            'role' => 'STAFF',
        ]);

        // ランダムユーザーの生成
        $count = 500; // 合計500人
        $chunkSize = 100; // 100人ずつ挿入
        $totalCount = 0;

        // トランザクションを使用してバルクインサート
        DB::transaction(function () use ($count, $chunkSize, &$totalCount) {
            for ($i = 0; $i < $count; $i += $chunkSize) {
                $users = [];

                // ユーザーを生成し、バルクインサート用に配列化
                for ($j = 0; $j < min($chunkSize, $count - $i); $j++) {
                    $users[] = [
                        'id' => Str::uuid(),
                        'username' => fake()->unique()->userName(),
                        'email' => fake()->unique()->safeEmail(),
                        'password' => Hash::make('password'),
                        'role' => fake()->randomElement(['ADMIN', 'MANAGER', 'STAFF']),
                        'last_login_date' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // 一括挿入
                User::insert($users);

                // 進捗を表示
                $totalCount = User::count();
                $this->command->info("Created " . min($i + $chunkSize, $count) . " users");
            }
        });

        // 最終的な総ユーザー数を表示
        $this->command->info("Total users after seeding: " . $totalCount);
    }
}
