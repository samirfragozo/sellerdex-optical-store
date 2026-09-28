<?php

namespace Database\Factories;

use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItemLensConfig>
 */
class SaleItemLensConfigFactory extends Factory
{
    protected $model = SaleItemLensConfig::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_item_id' => SaleItem::factory(),
            'lens_combination_id' => null,
            'type_name' => 'Monofocal',
            'technology_name' => 'Digital',
            'material_name' => 'Policarbonato',
            'combination_cost' => 60000,
            'combination_price' => 180000,
            'installation_price' => 3000,
        ];
    }
}
