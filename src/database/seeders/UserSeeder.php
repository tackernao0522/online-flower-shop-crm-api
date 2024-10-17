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
        // 管理者、マネージャー、スタッフの固定ユーザーを作成（既存でない場合のみ）
        $this->createUserIfNotExists('admin', 'admin@example.com', 'ADMIN');
        $this->createUserIfNotExists('manager', 'manager@example.com', 'MANAGER');
        $this->createUserIfNotExists('staff', 'staff@example.com', 'STAFF');

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
                        'username' => $this->generateUniqueUsername(),
                        'email' => $this->generateUniqueEmail(),
                        'password' => Hash::make('password'),
                        'role' => fake()->randomElement(['ADMIN', 'MANAGER', 'STAFF']),
                        'last_login_date' => null,
                        'is_active' => fake()->boolean(90), // 90%の確率でtrueを返す
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

    private function createUserIfNotExists(string $username, string $email, string $role): void
    {
        if (!User::where('username', $username)->exists()) {
            User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => $role,
                'is_active' => true,
            ]);
            $this->command->info("Created $role user: $username");
        } else {
            $this->command->info("$role user $username already exists. Skipping.");
        }
    }

    private function generateUniqueUsername(): string
    {
        do {
            $username = fake()->unique()->userName();
        } while (User::where('username', $username)->exists());

        return $username;
    }

    private function generateUniqueEmail(): string
    {
        do {
            $email = fake()->unique()->safeEmail();
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
