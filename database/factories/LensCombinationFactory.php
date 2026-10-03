<?php

namespace Database\Factories;

use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
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
            'installation_price' => fake()->numberBetween(0, 15000),
            'is_active' => true,
        ];
    }

    /** Sellable by default: one "all prescriptions" price at a lab, unless a test priced it itself. */
    public function configure(): static
    {
        return $this->afterCreating(function (LensCombination $combination): void {
            if (! $combination->prices()->withoutGlobalScopes()->exists()) {
                LensCombinationPrice::factory()->create(['lens_combination_id' => $combination->id]);
            }
        });
    }

    /** Priced at a lab with these values for every prescription. */
    public function priced(int $price, int $cost = 0): static
    {
        return $this->has(LensCombinationPrice::factory()->state(['price' => $price, 'cost' => $cost]), 'prices');
    }

    /** No price rows — not sellable. */
    public function unpriced(): static
    {
        return $this->afterCreating(fn (LensCombination $combination) => $combination->prices()->withoutGlobalScopes()->delete());
    }
}
