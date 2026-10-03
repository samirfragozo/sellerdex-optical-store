<?php

namespace Database\Factories;

use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensCombinationPrice>
 */
class LensCombinationPriceFactory extends Factory
{
    protected $model = LensCombinationPrice::class;

    /**
     * An "all prescriptions" row at the combination company's first active
     * lab (created when the company has none).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lens_combination_id' => LensCombination::factory()->unpriced(),
            'company_id' => fn (array $attributes) => LensCombination::withoutGlobalScopes()->find($attributes['lens_combination_id'])?->company_id,
            'supplier_id' => fn (array $attributes) => Supplier::withoutGlobalScopes()
                ->where('company_id', $attributes['company_id'])->where('is_laboratory', true)->where('is_active', true)
                ->orderBy('id')->value('id')
                ?? Supplier::factory()->laboratory()->create(['company_id' => $attributes['company_id']])->id,
            ...LensCombinationPrice::ALL_PRESCRIPTIONS,
            'cost' => fake()->numberBetween(20_000, 200_000),
            'price' => fake()->numberBetween(80_000, 600_000),
            'is_preferred' => true,
            'is_active' => true,
        ];
    }
}
