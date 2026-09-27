<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Tax;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['slug' => 'mi-optica'],
            ['name' => 'Mi Óptica', 'is_active' => true, 'plan' => 'free', 'onboarded_at' => now()],
        );

        if (! Tax::withoutGlobalScopes()->where('company_id', $company->id)->exists()) {
            Tax::seedDefaultsFor($company);
        }
    }
}
