<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only platform-wide, company-less data (the permission catalog and the
     * superadmin login) is seeded in production. A fixture company and its
     * reference/demo data have no place in a multi-tenant production database
     * — real companies are created via registration or the superadmin panel,
     * which already provision their own defaults. Everything else here is
     * local/testing convenience only.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperadminSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([
                CompanySeeder::class,
                PaymentMethodSeeder::class,
                ExpenseCategorySeeder::class,
                ProductCategorySeeder::class,
                ProductCatalogSeeder::class,
                DevSeeder::class,
            ]);
        }
    }
}
