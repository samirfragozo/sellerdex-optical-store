<?php

namespace Database\Factories;

use App\Models\SaleItemOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItemOption>
 */
class SaleItemOptionFactory extends Factory
{
    protected $model = SaleItemOption::class;

    public function definition(): array
    {
        return [
            'option_group_name' => fake()->word(),
            'option_name' => fake()->word(),
            'price' => fake()->numberBetween(0, 200_000),
            'cost' => fake()->numberBetween(0, 100_000),
        ];
    }
}
