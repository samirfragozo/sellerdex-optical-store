<?php

namespace Database\Factories;

use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensPriceBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensPriceBand>
 */
class LensPriceBandFactory extends Factory
{
    protected $model = LensPriceBand::class;

    /**
     * An "all prescriptions" band of a new combination.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lens_combination_id' => LensCombination::factory()->unpriced(),
            'company_id' => fn (array $attributes) => LensCombination::withoutGlobalScopes()->find($attributes['lens_combination_id'])?->company_id,
            ...LensCombinationPrice::ALL_PRESCRIPTIONS,
            'price' => fake()->numberBetween(80_000, 600_000),
        ];
    }
}
