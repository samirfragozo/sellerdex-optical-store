<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;

/**
 * Provisions the minimal generic data a brand-new company needs to be usable:
 * a default cash payment method, the structural product categories, and a
 * generic set of expense categories. Called once, right after a Company is
 * created (self-registration and the superadmin panel).
 */
class SeedCompanyDefaults
{
    public function handle(Company $company): void
    {
        PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Efectivo',
            'is_active' => true,
            'is_default' => true,
            'surcharge_percent' => 0,
            'sort_order' => 0,
        ]);

        foreach (ProductCategory::SYSTEM_CATEGORIES as $category) {
            ProductCategory::create([
                'company_id' => $company->id,
                'key' => $category['key'],
                'name' => $category['name'],
                'is_active' => true,
                'is_system' => true,
                'requires_prescription' => $category['requires_prescription'],
                'generates_lab_order' => $category['generates_lab_order'],
                'is_made_to_order' => $category['is_made_to_order'],
            ]);
        }

        foreach (ExpenseCategory::DEFAULT_NAMES as $name) {
            ExpenseCategory::create([
                'company_id' => $company->id,
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
