<?php

namespace Database\Factories;

use App\Models\LensType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensType>
 */
class LensTypeFactory extends Factory
{
    protected $model = LensType::class;

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
