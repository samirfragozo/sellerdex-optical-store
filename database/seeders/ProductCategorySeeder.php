<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Core categories. The `key` is the stable, code-facing identifier;
     * `name` is the editable Spanish label. Flags encode business rules.
     *
     * @var array<int,array{key:string,name:string,requires_prescription:bool,generates_lab_order:bool,is_made_to_order:bool}>
     */
    private const CATEGORIES = [
        ['key' => 'lens', 'name' => 'Lente', 'requires_prescription' => true, 'generates_lab_order' => true, 'is_made_to_order' => true],
        ['key' => 'frame', 'name' => 'Montura', 'requires_prescription' => false, 'generates_lab_order' => false, 'is_made_to_order' => false],
        ['key' => 'accessory', 'name' => 'Accesorio', 'requires_prescription' => false, 'generates_lab_order' => false, 'is_made_to_order' => false],
        ['key' => 'service', 'name' => 'Servicio', 'requires_prescription' => false, 'generates_lab_order' => false, 'is_made_to_order' => false],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            ProductCategory::updateOrCreate(
                ['key' => $category['key']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                    'is_system' => true,
                    'requires_prescription' => $category['requires_prescription'],
                    'generates_lab_order' => $category['generates_lab_order'],
                    'is_made_to_order' => $category['is_made_to_order'],
                ],
            );
        }
    }
}
