<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /** @var array<string, array<string, mixed>> */
    private const SYSTEM_CATEGORIES = [
        'Lente' => [
            'key' => 'lens',
            'is_system' => true,
            'requires_prescription' => true,
            'generates_lab_order' => true,
            'is_made_to_order' => true,
        ],
        'Montura' => [
            'key' => 'frame',
            'is_system' => true,
            'requires_prescription' => false,
            'generates_lab_order' => false,
            'is_made_to_order' => false,
        ],
        'Accesorio' => [
            'key' => 'accessory',
            'is_system' => true,
            'requires_prescription' => false,
            'generates_lab_order' => false,
            'is_made_to_order' => false,
        ],
        'Servicio' => [
            'key' => 'service',
            'is_system' => true,
            'requires_prescription' => false,
            'generates_lab_order' => false,
            'is_made_to_order' => false,
        ],
    ];

    public function run(): void
    {
        foreach (self::SYSTEM_CATEGORIES as $name => $attributes) {
            ProductCategory::updateOrCreate(
                ['name' => $name],
                array_merge(['is_active' => true], $attributes),
            );
        }
    }
}
