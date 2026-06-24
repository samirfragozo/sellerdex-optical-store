<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Lente', 'Montura', 'Accesorio', 'Servicio'] as $name) {
            ProductCategory::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
