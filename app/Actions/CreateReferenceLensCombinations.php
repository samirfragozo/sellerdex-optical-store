<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\LabSheetCell;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\Supplier;
use App\Support\ReferenceLensCatalog;
use Illuminate\Support\Arr;

/**
 * Turns the reference combinations the user kept in the onboarding lenses
 * step into LensType/LensTechnology/LensMaterial/LensCombination rows, costed
 * on the starting price sheet of each active lab the user said makes the lens
 * (every active lab by default).
 */
class CreateReferenceLensCombinations
{
    /**
     * @param  array<int, string>  $selectedKeys
     * @param  array<string, array{cost: int, price: int, installation_price: int, supplier_ids?: array<int, int|string>}>  $overrides  keyed by combination key
     */
    public function handle(Company $company, array $selectedKeys, array $overrides = []): void
    {
        $catalog = ReferenceLensCatalog::combinations();
        $labs = Supplier::query()->laboratories()->where('is_active', true)->orderBy('id')->get();

        foreach ($selectedKeys as $key) {
            $reference = $catalog[$key] ?? null;

            if ($reference === null) {
                continue;
            }

            $pricing = $overrides[$key] ?? $reference;

            $lensType = LensType::firstOrCreate(['name' => $reference['type']], ['kind' => $reference['kind'], 'is_active' => true]);
            $lensTechnology = LensTechnology::firstOrCreate(['name' => $reference['technology']], ['is_active' => true]);
            $lensMaterial = LensMaterial::firstOrCreate(['name' => $reference['material']], ['is_active' => true]);

            $combination = LensCombination::firstOrCreate(
                [
                    'lens_type_id' => $lensType->id,
                    'lens_technology_id' => $lensTechnology->id,
                    'lens_material_id' => $lensMaterial->id,
                ],
                [
                    'installation_price' => $pricing['installation_price'],
                    'is_active' => true,
                ],
            );

            $makers = isset($pricing['supplier_ids'])
                ? $labs->whereIn('id', array_map('intval', $pricing['supplier_ids']))->values()
                : $labs;

            // Onboarding starts with one "all prescriptions" sale band and, per lab, an "all prescriptions"
            // cell on its starting sheet; the shop refines ranges later.
            $combination->priceBands()->firstOrCreate(LensCombinationPrice::ALL_PRESCRIPTIONS, ['price' => $pricing['price']]);

            foreach ($makers as $position => $lab) {
                $sheet = $lab->priceSheets()->oldest('id')->first()
                    ?? $lab->priceSheets()->create(['name' => __('app.lab_sheets.onboarding_sheet')]);
                $column = $sheet->columns()->firstOrCreate(
                    ['lens_type_id' => $lensType->id, 'lens_technology_id' => $lensTechnology->id, 'add_min' => null, 'add_max' => null],
                    ['label' => "{$lensType->name} {$lensTechnology->name}"],
                );
                $row = $sheet->rows()->firstOrCreate(
                    ['lens_material_id' => $lensMaterial->id, ...Arr::except(LensCombinationPrice::ALL_PRESCRIPTIONS, ['add_min', 'add_max'])],
                    ['label' => $lensMaterial->name],
                );

                LabSheetCell::firstOrCreate(
                    ['lab_sheet_row_id' => $row->id, 'lab_sheet_column_id' => $column->id],
                    ['cost' => $pricing['cost'], 'is_preferred' => $position === 0, 'is_active' => true],
                );
            }
        }
    }
}
