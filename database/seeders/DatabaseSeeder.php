<?php

namespace Database\Seeders;

use App\Domain\Debate\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('12345678');

        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => $defaultPassword,
                'role' => UserRole::Superadmin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        foreach (range(1, 10) as $judgeNumber) {
            User::query()->firstOrCreate(
                ['email' => "judge{$judgeNumber}@example.com"],
                [
                    'name' => "Demo Judge {$judgeNumber}",
                    'password' => $defaultPassword,
                    'role' => UserRole::Judge,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
