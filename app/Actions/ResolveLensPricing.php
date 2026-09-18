<?php

namespace App\Actions;

use App\Models\LensCombination;
use App\Models\LensPackage;
use App\Models\LensTreatment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validate a chosen lens configuration (Tipo, Tecnología, Material, Paquete,
 * Tratamientos) and resolve its total sellable price/cost.
 */
class ResolveLensPricing
{
    /**
     * @param  array<int, int>  $treatmentIds
     * @return array{combination: LensCombination, package: LensPackage, treatments: Collection<int, LensTreatment>, price: int, cost: int}
     */
    public function handle(int $lensTypeId, int $lensTechnologyId, int $lensMaterialId, int $lensPackageId, array $treatmentIds): array
    {
        $combination = LensCombination::forSelection($lensTypeId, $lensTechnologyId, $lensMaterialId);

        if ($combination === null) {
            throw ValidationException::withMessages([
                'lens' => __('app.pos.lens_form.invalid_combination'),
            ]);
        }

        $package = LensPackage::query()->where('id', $lensPackageId)->where('is_active', true)->first();

        if ($package === null) {
            throw ValidationException::withMessages([
                'lens' => __('app.pos.lens_form.invalid_package'),
            ]);
        }

        $treatments = LensTreatment::query()
            ->whereIn('id', $treatmentIds)
            ->where('is_active', true)
            ->get();

        if ($treatments->count() !== count(array_unique($treatmentIds))) {
            throw ValidationException::withMessages([
                'lens' => __('app.pos.lens_form.invalid_treatment'),
            ]);
        }

        $price = $combination->price + $combination->installation_price + $package->price + (int) $treatments->sum('price');
        $cost = $combination->cost + $package->cost + (int) $treatments->sum('cost');

        return compact('combination', 'package', 'treatments', 'price', 'cost');
    }
}
