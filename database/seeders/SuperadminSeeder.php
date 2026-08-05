<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Seeds the superadmin login (company_id null) from SUPERADMIN_EMAIL /
 * SUPERADMIN_PASSWORD. Required in production; silently skipped elsewhere
 * when unset, so local/testing runs don't need these vars.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.superadmin.email');
        $password = config('app.superadmin.password');

        if (! $email || ! $password) {
            if (app()->isProduction()) {
                throw new RuntimeException('SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD must be set to seed production.');
            }

            return;
        }

        $superadmin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Superadmin', 'password' => Hash::make($password), 'is_active' => true, 'company_id' => null],
        );

        $superadmin->assignRole(User::ROLE_SUPERADMIN);
    }
}
