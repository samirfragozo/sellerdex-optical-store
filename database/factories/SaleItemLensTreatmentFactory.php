<?php

namespace Database\Factories;

use App\Models\SaleItemLensConfig;
use App\Models\SaleItemLensTreatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItemLensTreatment>
 */
class SaleItemLensTreatmentFactory extends Factory
{
    protected $model = SaleItemLensTreatment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_item_lens_config_id' => SaleItemLensConfig::factory(),
            'lens_treatment_id' => null,
            'name' => 'Antirreflejo',
            'price' => 50000,
            'cost' => 20000,
        ];
    }
}
