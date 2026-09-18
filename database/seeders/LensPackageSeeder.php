<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LensPackage;
use Illuminate\Database\Seeder;

class LensPackageSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->each(function (Company $company): void {
            LensPackage::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Básico'],
                ['price' => 0, 'cost' => 0, 'is_active' => true, 'sort_order' => 1],
            );
        });
    }
}
