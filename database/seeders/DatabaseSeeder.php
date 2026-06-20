<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Essential, production-safe reference data is always seeded. Demo/sample
     * data (test users, etc.) is only seeded outside of production via DevSeeder.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            BusinessSettingSeeder::class,
            PaymentMethodSeeder::class,
            ExpenseCategorySeeder::class,
            ProductCategorySeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DevSeeder::class);
        }
    }
}
