<?php

namespace Database\Factories;

use App\Enums\TaxTreatment;
use App\Models\Company;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tax> */
class TaxFactory extends Factory
{
    public function definition(): array
    {
        return [
            // BelongsToCompany only fills company_id from the authenticated user; tests
            // that create taxes unauthenticated need the factory to supply one itself.
            'company_id' => Company::factory(),
            'name' => 'IVA '.fake()->unique()->numberBetween(1, 99).'%',
            'rate' => 19,
            'treatment' => TaxTreatment::Taxed,
            'dian_code' => '01',
            'is_default' => false,
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
