<?php

namespace Database\Factories;

use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LabSheetColumn;
use App\Models\LabSheetRow;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensPriceBand;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\Supplier;
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

    /**
     * Sellable by default: one "all prescriptions" sale band and a preferred
     * "all prescriptions" cost cell at the company's first active lab
     * (created when it has none), unless a test priced it itself.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (LensCombination $combination): void {
            if (! $combination->priceBands()->withoutGlobalScopes()->exists()) {
                LensPriceBand::factory()->create(['lens_combination_id' => $combination->id]);
            }

            if (! $combination->cells()->withoutGlobalScopes()->exists()) {
                $this->createCostCell($combination);
            }
        });
    }

    /** Sold at $price and costing $cost at a lab, for every prescription. */
    public function priced(int $price, int $cost = 0): static
    {
        return $this->has(LensPriceBand::factory()->state(['price' => $price]), 'priceBands')
            ->afterCreating(fn (LensCombination $combination) => $combination->cells()->withoutGlobalScopes()->update(['cost' => $cost]));
    }

    /** No sale band nor cost cell — not sellable. */
    public function unpriced(): static
    {
        return $this->afterCreating(function (LensCombination $combination): void {
            $combination->priceBands()->withoutGlobalScopes()->delete();
            $combination->cells()->withoutGlobalScopes()->delete();
        });
    }

    /** An "all prescriptions" cell for this lens on a new sheet of the company's first active lab. */
    private function createCostCell(LensCombination $combination): void
    {
        $labId = Supplier::withoutGlobalScopes()
            ->where('company_id', $combination->company_id)->where('is_laboratory', true)->where('is_active', true)
            ->orderBy('id')->value('id')
            ?? Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id])->id;
        $sheet = LabPriceSheet::factory()->create(['supplier_id' => $labId]);

        LabSheetCell::factory()->create([
            'lab_sheet_row_id' => LabSheetRow::factory()->state(['lab_price_sheet_id' => $sheet->id, 'lens_material_id' => $combination->lens_material_id]),
            'lab_sheet_column_id' => LabSheetColumn::factory()->state([
                'lab_price_sheet_id' => $sheet->id,
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
            ]),
            'is_preferred' => true,
        ]);
    }
}
