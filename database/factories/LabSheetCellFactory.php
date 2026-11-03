<?php

namespace Database\Factories;

use App\Models\LabSheetCell;
use App\Models\LabSheetColumn;
use App\Models\LabSheetRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabSheetCell>
 */
class LabSheetCellFactory extends Factory
{
    protected $model = LabSheetCell::class;

    /**
     * A cell on a new row and a new column of one shared sheet; the model
     * fills in its sheet and lens from them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_sheet_row_id' => LabSheetRow::factory(),
            'lab_sheet_column_id' => fn (array $attributes) => LabSheetColumn::factory()->create([
                'lab_price_sheet_id' => LabSheetRow::withoutGlobalScopes()->find($attributes['lab_sheet_row_id'])?->lab_price_sheet_id,
            ])->id,
            'company_id' => fn (array $attributes) => LabSheetRow::withoutGlobalScopes()->find($attributes['lab_sheet_row_id'])?->company_id,
            'cost' => fake()->numberBetween(20_000, 200_000),
            'is_preferred' => false,
            'is_active' => true,
        ];
    }
}
