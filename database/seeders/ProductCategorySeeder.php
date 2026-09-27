<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (ProductCategory::SYSTEM_CATEGORIES as $category) {
            ProductCategory::updateOrCreate(
                ['key' => $category['key']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                    'is_system' => true,
                    'requires_prescription' => $category['requires_prescription'],
                    'generates_lab_order' => $category['generates_lab_order'],
                    'is_made_to_order' => $category['is_made_to_order'],
                    'default_tax_id' => ProductCategory::defaultTaxIdFor($category['key'], Auth::user()?->company_id),
                ],
            );
        }
    }
}
