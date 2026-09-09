<?php

namespace Database\Factories;

use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    protected $model = Option::class;

    public function definition(): array
    {
        return [
            'option_group_id' => OptionGroup::factory(),
            'name' => fake()->word(),
            'price' => fake()->numberBetween(0, 200_000),
            'cost' => fake()->numberBetween(0, 100_000),
            'is_active' => true,
            'sort_order' => null,
        ];
    }
}
