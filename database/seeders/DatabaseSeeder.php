<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Support\ReferenceKit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only platform-wide, company-less data (the permission catalog and the
     * superadmin login) is seeded in production. A fixture company has no
     * place in a multi-tenant production database — real companies are
     * created via registration or the superadmin panel, which already
     * provision their own defaults, including the reference product catalog
     * (see SeedCompanyDefaults). Everything else here is local/testing
     * convenience only.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperadminSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([CompanySeeder::class, DevSeeder::class]);

            // Tenant fixtures belong to the demo company: BelongsToCompany fills company_id from the logged-in user.
            Auth::setUser(User::where('email', 'admin@optica.test')->firstOrFail());
            $this->call([
                PaymentMethodSeeder::class,
                ExpenseCategorySeeder::class,
                ProductCategorySeeder::class,
                ProductCatalogSeeder::class,
            ]);
            ReferenceKit::installFor(Company::firstOrFail());
            Auth::forgetUser();
        }
    }
}
