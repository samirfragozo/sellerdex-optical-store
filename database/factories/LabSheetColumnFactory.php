<?php

namespace Database\Factories;

use App\Models\LabPriceSheet;
use App\Models\LabSheetColumn;
use App\Models\LensTechnology;
use App\Models\LensType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabSheetColumn>
 */
class LabSheetColumnFactory extends Factory
{
    protected $model = LabSheetColumn::class;

    /**
     * A column of a new sheet for a new type + technology, any addition.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_price_sheet_id' => LabPriceSheet::factory(),
            'company_id' => fn (array $attributes) => LabPriceSheet::withoutGlobalScopes()->find($attributes['lab_price_sheet_id'])?->company_id,
            'label' => fake()->words(2, true),
            'lens_type_id' => LensType::factory(),
            'lens_technology_id' => LensTechnology::factory(),
            'add_min' => null,
            'add_max' => null,
            'sort_order' => 0,
        ];
    }
}
