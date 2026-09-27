<?php

namespace Database\Factories;

use App\Enums\TaxTreatment;
use App\Models\Company;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/** @extends Factory<Tax> */
class TaxFactory extends Factory
{
    public function definition(): array
    {
        return [
            // BelongsToCompany only fills company_id (via ??=) from the authenticated user
            // on create(), not in the factory's attribute array, so an acting-as test would
            // otherwise get a tax in a brand-new company instead of the current one.
            'company_id' => fn () => Auth::user()?->company_id ?? Company::factory(),
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
