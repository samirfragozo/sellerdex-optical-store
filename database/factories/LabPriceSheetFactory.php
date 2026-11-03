<?php

namespace Database\Factories;

use App\Models\LabPriceSheet;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabPriceSheet>
 */
class LabPriceSheetFactory extends Factory
{
    protected $model = LabPriceSheet::class;

    /**
     * A sheet of a new lab.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory()->laboratory(),
            'company_id' => fn (array $attributes) => Supplier::withoutGlobalScopes()->find($attributes['supplier_id'])?->company_id,
            'name' => fake()->words(2, true),
        ];
    }
}
