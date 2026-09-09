<?php

namespace Database\Factories;

use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionGroup>
 */
class OptionGroupFactory extends Factory
{
    protected $model = OptionGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'selection_type' => 'single',
            'is_required' => true,
            'is_active' => true,
        ];
    }
}
