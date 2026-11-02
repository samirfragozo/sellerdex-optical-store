<?php

namespace Database\Factories;

use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensMaterial;
use App\Models\LensPriceBand;
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

    /** Sellable by default: one "all prescriptions" sale band and cost row at a lab, unless a test priced it itself. */
    public function configure(): static
    {
        return $this->afterCreating(function (LensCombination $combination): void {
            if (! $combination->priceBands()->withoutGlobalScopes()->exists()) {
                LensPriceBand::factory()->create(['lens_combination_id' => $combination->id]);
            }

            if (! $combination->prices()->withoutGlobalScopes()->exists()) {
                LensCombinationPrice::factory()->create(['lens_combination_id' => $combination->id]);
            }
        });
    }

    /** Sold at $price and costing $cost at a lab, for every prescription. */
    public function priced(int $price, int $cost = 0): static
    {
        return $this->has(LensPriceBand::factory()->state(['price' => $price]), 'priceBands')
            ->has(LensCombinationPrice::factory()->state(['cost' => $cost]), 'prices');
    }

    /** No sale band nor cost row — not sellable. */
    public function unpriced(): static
    {
        return $this->afterCreating(function (LensCombination $combination): void {
            $combination->priceBands()->withoutGlobalScopes()->delete();
            $combination->prices()->withoutGlobalScopes()->delete();
        });
    }
}
