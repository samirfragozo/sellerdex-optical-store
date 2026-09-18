<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LensPackage;
use Illuminate\Database\Seeder;

class LensPackageSeeder extends Seeder
{
    /** Entry point for `php artisan db:seed`: backfills every existing company. */
    public function run(): void
    {
        Company::query()->each(fn (Company $company) => $this->handle($company->id));
    }

    /** Entry point for provisioning a real company's packages (see SeedCompanyDefaults). */
    public function handle(int $companyId): void
    {
        LensPackage::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Básico'],
            ['price' => 0, 'cost' => 0, 'is_active' => true, 'sort_order' => 1],
        );
    }
}
