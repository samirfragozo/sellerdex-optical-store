<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Lente', 'Montura', 'Filtro', 'Accesorio', 'Promoción', 'Servicio'] as $name) {
            ProductCategory::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
