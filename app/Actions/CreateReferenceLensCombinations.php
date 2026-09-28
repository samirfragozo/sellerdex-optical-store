<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Support\ReferenceLensCatalog;

/**
 * Turns the reference combinations the user kept in the onboarding lenses
 * step into LensType/LensTechnology/LensMaterial/LensCombination rows.
 */
class CreateReferenceLensCombinations
{
    /**
     * @param  array<int, string>  $selectedKeys
     * @param  array<string, array{cost: int, price: int, installation_price: int}>  $overrides  keyed by combination key
     */
    public function handle(Company $company, array $selectedKeys, array $overrides = []): void
    {
        $catalog = ReferenceLensCatalog::combinations();

        foreach ($selectedKeys as $key) {
            $reference = $catalog[$key] ?? null;

            if ($reference === null) {
                continue;
            }

            $pricing = $overrides[$key] ?? $reference;

            $lensType = LensType::firstOrCreate(['name' => $reference['type']], ['kind' => $reference['kind'], 'is_active' => true]);
            $lensTechnology = LensTechnology::firstOrCreate(['name' => $reference['technology']], ['is_active' => true]);
            $lensMaterial = LensMaterial::firstOrCreate(['name' => $reference['material']], ['is_active' => true]);

            LensCombination::firstOrCreate(
                [
                    'lens_type_id' => $lensType->id,
                    'lens_technology_id' => $lensTechnology->id,
                    'lens_material_id' => $lensMaterial->id,
                ],
                [
                    'cost' => $pricing['cost'],
                    'price' => $pricing['price'],
                    'installation_price' => $pricing['installation_price'],
                    'is_active' => true,
                ],
            );
        }
    }
}
