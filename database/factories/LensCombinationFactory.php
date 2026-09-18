<?php

namespace Database\Factories;

use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensCombination>
 */
class LensCombinationFactory extends Factory
{
    protected $model = LensCombination::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lens_type_id' => LensType::factory(),
            'lens_technology_id' => LensTechnology::factory(),
            'lens_material_id' => LensMaterial::factory(),
            'cost' => fake()->numberBetween(20000, 200000),
            'price' => fake()->numberBetween(80000, 600000),
            'installation_price' => fake()->numberBetween(0, 15000),
            'is_active' => true,
        ];
    }
}
