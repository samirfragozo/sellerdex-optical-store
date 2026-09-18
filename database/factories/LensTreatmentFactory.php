<?php

namespace Database\Factories;

use App\Models\LensTreatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensTreatment>
 */
class LensTreatmentFactory extends Factory
{
    protected $model = LensTreatment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->numberBetween(10000, 300000),
            'cost' => fake()->numberBetween(5000, 150000),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
