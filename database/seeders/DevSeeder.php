<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for local/testing environments only. Never run this in production:
 * it creates accounts with well-known credentials.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@optica.test'],
            ['name' => 'Admin', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $admin->assignRole(User::ROLE_ADMIN);

        $seller = User::firstOrCreate(
            ['email' => 'seller@optica.test'],
            ['name' => 'Seller', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $seller->assignRole(User::ROLE_SELLER);
    }
}
