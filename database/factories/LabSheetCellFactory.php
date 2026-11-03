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
     * A cell on a row and a column of one shared sheet: given only a column
     * (id or model), the new row goes on that column's sheet; otherwise the
     * new column goes on the row's sheet. The model fills in its sheet,
     * company and lens.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_sheet_row_id' => function (array $attributes): LabSheetRowFactory {
                $column = $attributes['lab_sheet_column_id'] ?? null;
                $column = is_numeric($column) ? LabSheetColumn::withoutGlobalScopes()->find($column) : $column;

                return $column instanceof LabSheetColumn
                    ? LabSheetRow::factory()->state(['lab_price_sheet_id' => $column->lab_price_sheet_id])
                    : LabSheetRow::factory();
            },
            'lab_sheet_column_id' => fn (array $attributes) => LabSheetColumn::factory()->state([
                'lab_price_sheet_id' => LabSheetRow::withoutGlobalScopes()->find($attributes['lab_sheet_row_id'])?->lab_price_sheet_id,
            ]),
            'company_id' => fn (array $attributes) => LabSheetRow::withoutGlobalScopes()->find($attributes['lab_sheet_row_id'])?->company_id,
            'cost' => fake()->numberBetween(20_000, 200_000),
            'is_preferred' => false,
            'is_active' => true,
        ];
    }
}
