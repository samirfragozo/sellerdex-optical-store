<?php

namespace Database\Factories;

use App\Models\LabPriceSheet;
use App\Models\LabSheetRow;
use App\Models\LensCombinationPrice;
use App\Models\LensMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<LabSheetRow>
 */
class LabSheetRowFactory extends Factory
{
    protected $model = LabSheetRow::class;

    /**
     * An "all prescriptions" row of a new sheet for a new material.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_price_sheet_id' => LabPriceSheet::factory(),
            'company_id' => fn (array $attributes) => LabPriceSheet::withoutGlobalScopes()->find($attributes['lab_price_sheet_id'])?->company_id,
            'label' => fake()->words(2, true),
            'lens_material_id' => LensMaterial::factory(),
            ...Arr::except(LensCombinationPrice::ALL_PRESCRIPTIONS, ['add_min', 'add_max']),
            'sort_order' => 0,
        ];
    }
}
