<?php

namespace Database\Factories;

use App\Models\LensMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensMaterial>
 */
class LensMaterialFactory extends Factory
{
    protected $model = LensMaterial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
